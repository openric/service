<?php

/**
 * WizardSuggestController — AI-assisted RiC modelling suggestions.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing iSystems. AGPL 3.0.
 *
 * POST /api/ric/v1/wizard/suggest  { "description": "..." }
 *
 * Takes a plain-language description of archival material and returns a wizard
 * scenario (same schema as the static scenarios) proposing a RiC-CM 1.0 model.
 * The LLM call is proxied THROUGH the AHG gateway (never a node directly), per
 * the standing AI-gateway rule. The model's output is validated against the
 * RiC-CM 1.0 entity set and the server's relation vocabulary before it is
 * returned — an invalid suggestion is rejected, never shown.
 *
 * Inert until configured: returns 503 unless OPENRIC_AI_URL / OPENRIC_AI_KEY /
 * OPENRIC_AI_MODEL are set. Creates nothing — it only proposes a model the user
 * then walks (and optionally creates) in the wizard.
 */

namespace AhgRic\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WizardSuggestController extends Controller
{
    /** Valid RiC-CM 1.0 entity codes a suggestion may use. */
    private const ENTITIES = [
        'RiC-E02', 'RiC-E03', 'RiC-E04', 'RiC-E05', 'RiC-E06', 'RiC-E07', 'RiC-E08',
        'RiC-E09', 'RiC-E10', 'RiC-E11', 'RiC-E15', 'RiC-E16', 'RiC-E17', 'RiC-E18', 'RiC-E22', '—',
    ];

    /** Relation codes the reference server accepts (must match ric_relation_type). */
    private const RELATIONS = [
        'has_creator', 'has_instantiation', 'has_or_had_location', 'has_or_had_subject',
        'results_from', 'performed_by', 'is_regulated_by', 'has_part', 'held_by',
        'has_mandate', 'is_associated_with', 'documents', 'has_accumulator',
        'is_copy_of', 'is_original_of', 'has_derived_instantiation',
    ];

    public function suggest(Request $request): JsonResponse
    {
        $url   = (string) env('OPENRIC_AI_URL', '');
        $key   = (string) env('OPENRIC_AI_KEY', '');
        $model = (string) env('OPENRIC_AI_MODEL', '');

        if ($url === '' || $key === '' || $model === '') {
            return response()->json([
                'error'   => 'not_configured',
                'message' => 'AI model suggestions are not enabled on this server. Set OPENRIC_AI_URL, OPENRIC_AI_KEY and OPENRIC_AI_MODEL to enable.',
            ], 503);
        }

        $desc = trim((string) $request->input('description', ''));
        if (mb_strlen($desc) < 8) {
            return response()->json(['error' => 'too_short', 'message' => 'Describe the material in a sentence or two.'], 422);
        }
        $desc = mb_substr($desc, 0, 1500);

        try {
            $resp = Http::withToken($key)
                ->timeout((int) env('OPENRIC_AI_TIMEOUT', 45))
                ->acceptJson()
                ->post($url, [
                    'model'    => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are an archival modelling assistant. Output ONLY strict JSON — no markdown, no commentary. /no_think'],
                        ['role' => 'user', 'content' => $this->buildPrompt($desc)],
                    ],
                    'temperature' => 0.2,
                    'stream'      => false,
                ]);
        } catch (\Throwable $e) {
            Log::error('[wizard/suggest] gateway call failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'gateway_unreachable', 'message' => 'The model service is unavailable right now. Try again shortly.'], 502);
        }

        if (!$resp->ok()) {
            Log::warning('[wizard/suggest] gateway non-2xx', ['status' => $resp->status()]);
            return response()->json(['error' => 'gateway_error', 'message' => 'The model service returned an error.', 'status' => $resp->status()], 502);
        }

        $scenario = $this->parseScenario($this->extractText($resp->json()));
        if (!$scenario) {
            return response()->json(['error' => 'unparseable', 'message' => 'The model did not return a usable model. Try rephrasing your description.'], 422);
        }

        $scenario = $this->sanitize($scenario);
        $problems = $this->validateScenario($scenario);
        if ($problems) {
            return response()->json([
                'error'   => 'invalid_model',
                'message' => 'The suggested model used RiC codes that are not valid here; please rephrase and try again.',
                'detail'  => array_slice($problems, 0, 8),
            ], 422);
        }

        $scenario['generated'] = true;
        $scenario['server_default'] = url('/api/ric/v1');

        // Anonymous demand signal: a successful AI suggestion was produced.
        \AhgRic\Support\UsageRecorder::bump('ai_suggest', $model);

        return response()->json($scenario);
    }

    private function buildPrompt(string $desc): string
    {
        $entities = "RiC-E03 Record Set, RiC-E04 Record, RiC-E05 Record Part, RiC-E06 Instantiation, "
            . "RiC-E07 Agent (use for Person/Corporate Body), RiC-E15 Activity, RiC-E16 Rule, RiC-E22 Place";
        $relations = implode(', ', array_filter(self::RELATIONS, fn ($r) => $r !== ''));

        return <<<PROMPT
You are an expert archival describer who models material in Records in Contexts (RiC-CM 1.0).
Given a description of archival material, produce a STEP-BY-STEP wizard scenario in STRICT JSON
(no markdown, no prose outside the JSON) that walks an archivist through modelling it.

Use ONLY these entity codes: {$entities}.
Use ONLY these relation_type codes in capture calls: {$relations}.

Output JSON with exactly this shape:
{
  "id": "ai-suggestion",
  "title": "<short title>",
  "intro": "<one-sentence intro>",
  "question": "<the modelling question>",
  "steps": [
    {
      "id": "<slug>",
      "prompt": "<the decision>",
      "next": "<id of next step, or omit on the last step>",
      "choices": [
        { "label": "<entity name>", "entity": "RiC-Exx", "correct": true,  "why": "<why it fits>" },
        { "label": "<other option>", "entity": "RiC-Exx", "correct": false, "why": "<why it does not fit>" }
      ],
      "capture": [
        { "comment": "<what this creates>", "method": "POST", "path": "/records",
          "body": { "title": "<example>", "level": "item" }, "save_as": "item" }
      ]
    }
  ],
  "outcome": { "verdict": "<one line>", "summary": "<2-3 sentences>" }
}

Rules:
- 3 to 6 steps. Each step has >=2 choices and exactly one with "correct": true.
- Capture paths must be one of: /records, /record-parts, /record-sets, /agents, /places, /rules, /activities, /instantiations, /relations.
- A /record-parts body needs "parent_id"; relate entities with /relations using {"subject_id","object_id","relation_type"}.
- Reference ids created in earlier steps as "{{<save_as>.id}}".
- Keep it accurate to RiC-CM 1.0. Output JSON only.

Material to model:
{$desc}
PROMPT;
    }

    /** Pull the generated text out of whatever envelope the gateway returns. */
    private function extractText($body): string
    {
        if (is_string($body)) return $body;
        if (!is_array($body)) return '';
        return (string) (
            $body['text']
            ?? $body['response']
            ?? $body['output']
            ?? $body['content']
            ?? ($body['choices'][0]['message']['content'] ?? null)
            ?? ($body['choices'][0]['text'] ?? null)
            ?? ''
        );
    }

    /** Extract the first balanced JSON object from the model text and decode it. */
    private function parseScenario(string $text): ?array
    {
        // Strip reasoning blocks some models emit (e.g. qwen3 <think>…</think>).
        $text = preg_replace('/<think>.*?<\/think>/s', '', $text) ?? $text;
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) return null;
        $json = substr($text, $start, $end - $start + 1);
        $data = json_decode($json, true);
        return (is_array($data) && !empty($data['steps'])) ? $data : null;
    }

    /**
     * Repair the model's most common harmless mistakes: drop step.next /
     * choice.next that point at a non-existent step (the wizard engine then
     * falls through to the next step in order / the outcome). Hard RiC-code
     * errors are NOT repaired — they fail validation instead.
     */
    private function sanitize(array $s): array
    {
        $ids = [];
        foreach ($s['steps'] as $st) if (!empty($st['id'])) $ids[$st['id']] = true;
        foreach ($s['steps'] as &$st) {
            if (!empty($st['next']) && empty($ids[$st['next']])) unset($st['next']);
            foreach ($st['choices'] ?? [] as &$c) {
                if (!empty($c['next']) && empty($ids[$c['next']])) unset($c['next']);
            }
            unset($c);
        }
        unset($st);
        return $s;
    }

    /** Validate a suggested scenario against RiC-CM 1.0 + the relation vocabulary. */
    private function validateScenario(array $s): array
    {
        $errs = [];
        if (empty($s['steps']) || !is_array($s['steps'])) return ['no steps'];
        $allowedPaths = ['/records', '/record-parts', '/record-sets', '/agents', '/places', '/rules', '/activities', '/instantiations', '/relations'];
        foreach ($s['steps'] as $st) {
            $at = 'step ' . ($st['id'] ?? '?');
            if (empty($st['prompt'])) $errs[] = "$at: no prompt";
            $hasCorrect = false;
            foreach ($st['choices'] ?? [] as $c) {
                if (!empty($c['correct'])) $hasCorrect = true;
                if (!empty($c['entity']) && !in_array($c['entity'], self::ENTITIES, true)) $errs[] = "$at: bad entity {$c['entity']}";
            }
            if (!$hasCorrect) $errs[] = "$at: no correct choice";
            foreach ($st['capture'] ?? [] as $call) {
                if (empty($call['path']) || !in_array($call['path'], $allowedPaths, true)) $errs[] = "$at: bad path " . ($call['path'] ?? '');
                $rt = $call['body']['relation_type'] ?? null;
                if ($rt && !in_array($rt, self::RELATIONS, true)) $errs[] = "$at: bad relation_type $rt";
            }
        }
        return $errs;
    }
}
