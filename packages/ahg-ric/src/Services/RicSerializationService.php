<?php

/**
 * RicSerializationService - RIC-O JSON-LD serialization
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgRic\Services;

use AhgCore\Constants\TermId;
use AhgCore\Services\SettingHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Service for serializing AtoM entities to RiC-O JSON-LD format.
 * 
 * Supports:
 * - ISAAR(CPF) for Agents
 * - ISDF for Functions
 * - ISAD for Records
 * - ISDIAH for Repositories
 * - ISCAP for Security/Access
 * - Spectrum for Conservation
 * - GRAP for Heritage Assets
 */
class RicSerializationService
{
    private string $baseUri;
    private string $instanceId;
    private string $fusekiEndpoint;

    // RIC-O Namespace
    private const RICO_NS = 'https://www.ica.org/standards/RiC/ontology#';
    private const RDF_NS = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    private const RDFS_NS = 'http://www.w3.org/2000/01/rdf-schema#';
    private const XSD_NS = 'http://www.w3.org/2001/XMLSchema#';
    // OpenRiC namespaces (per spec v0.37.x §4 normative prefixes).
    private const OPENRIC_NS  = 'https://openric.org/ns/v1#';
    private const OPENRICX_NS = 'https://openric.org/ns/ext/v1#';
    private const SKOS_NS     = 'http://www.w3.org/2004/02/skos/core#';
    private const DCTERMS_NS  = 'http://purl.org/dc/terms/';
    private const OWL_NS      = 'http://www.w3.org/2002/07/owl#';

    /**
     * Canonical OpenRiC JSON-LD @context binding (per spec v0.37.x §4).
     * All emitted records SHOULD bind these prefixes; openricx and dcterms
     * are required for OpenRiC v0.37+ remediation.
     */
    private function ricoContext(): array
    {
        return [
            'rico'     => self::RICO_NS,
            'openric'  => self::OPENRIC_NS,
            'openricx' => self::OPENRICX_NS,
            'rdf'      => self::RDF_NS,
            'rdfs'     => self::RDFS_NS,
            'xsd'      => self::XSD_NS,
            'skos'     => self::SKOS_NS,
            'dcterms'  => self::DCTERMS_NS,
            'owl'      => self::OWL_NS,
        ];
    }

    // Level to RIC mapping
    private array $levelToRic = [
        'fonds' => 'RecordSet',
        'subfonds' => 'RecordSet',
        'collection' => 'RecordSet',
        'series' => 'RecordSet',
        'subseries' => 'RecordSet',
        'file' => 'RecordSet',
        'item' => 'Record',
        'part' => 'RecordPart',
    ];

    // Actor type to RIC mapping
    private array $actorTypeToRic = [
        'corporate body' => 'CorporateBody',
        'person' => 'Person',
        'family' => 'Family',
    ];

    // Thing type to RIC mapping (boxes, containers, etc.)
    private array $thingTypeToRic = [
        'box' => 'Thing',
        'container' => 'Thing',
        'shelf_unit' => 'Thing',
        'cabinet' => 'Thing',
        'vault' => 'Thing',
        'equipment' => 'Thing',
    ];

    // Event type → activity-type IRI mapping (per OpenRiC mapping spec v0.37 §6.5).
    // RiC-O 1.1 has no concrete Activity subclasses (Production, Accumulation, etc.
    // do not exist as classes). Every event is rico:Activity; the kind is carried
    // in rico:hasActivityType pointing at an IRI from the OpenRiC vocabulary
    // <https://openric.org/vocab/activity-type/>.
    private array $eventTypeToActivityType = [
        'creation'     => 'production',
        'production'   => 'production',
        'contribution' => 'production',
        'accumulation' => 'accumulation',
        'collection'   => 'accumulation',
        'custody'      => 'custody',
        'mandate'      => 'custody',          // legacy AtoM event-type; closest fit
        'publication'  => 'publication',
        'reproduction' => 'reproduction',
        'transfer'     => 'transfer',
    ];

    /** Resolve a source event_type string to a full activity-type IRI. Returns null if unknown. */
    private function activityTypeIri(?string $sourceEventType): ?string
    {
        if (!$sourceEventType) return null;
        $slug = $this->eventTypeToActivityType[strtolower($sourceEventType)] ?? null;
        return $slug ? "https://openric.org/vocab/activity-type/{$slug}" : null;
    }

    public function __construct()
    {
        $this->baseUri = config('app.url', 'http://localhost');
        $this->instanceId = SettingHelper::get('ahg_ric_instance_id', 'default');
        $this->fusekiEndpoint = config('ahg-ric.fuseki_endpoint', 'http://localhost:3030/openric');
    }

    /**
     * Serialize an Information Object (Record) to RIC-O JSON-LD
     */
    public function serializeRecord(int $ioId, array $options = []): array
    {
        $io = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', 'io.id', '=', 'i18n.id')
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->leftJoin('term as level', 'io.level_of_description_id', '=', 'level.id')
            // Filter the level name to English: term_i18n carries ~30 culture
            // rows per term, and without this filter the join picked an
            // arbitrary culture (e.g. "Dio"), so the level→RiC-type mapping
            // silently fell back to Record. The $levelToRic keys are English.
            ->leftJoin('term_i18n as level_i18n', function ($j) {
                $j->on('level.id', '=', 'level_i18n.id')
                    ->where('level_i18n.culture', '=', 'en');
            })
            ->where('io.id', $ioId)
            ->select([
                'io.*',
                'i18n.*',
                'slug.slug',
                'level_i18n.name as level_name',
            ])
            ->first();

        if (!$io) {
            return ['error' => 'Information Object not found'];
        }

        // Level names are stored capitalised ("Fonds", "Item", "Part") and may
        // vary by culture; the $levelToRic keys are lowercase. Lowercase the
        // lookup so e.g. "Part" → RecordPart deterministically (matches the
        // hierarchy serializer below, which already lowercases).
        $ricType = $this->levelToRic[strtolower((string) $io->level_name)] ?? 'Record';

        $record = [
            '@context' => $this->ricoContext(),
            '@id' => $this->baseUri . '/informationobject/' . $io->slug,
            '@type' => self::RICO_NS . $ricType,
            'rico:type' => $ricType,
        ];

        // Title
        if (!empty($io->title)) {
            $record['rico:title'] = $io->title;
        }

        // Identifier
        if (!empty($io->identifier)) {
            $record['rico:identifier'] = $io->identifier;
        }

        // Description
        if (!empty($io->scope_and_content)) {
            $record['openricx:description'] = $io->scope_and_content;
        }

        // Dates
        $dates = $this->getDatesForRecord($ioId);
        if (!empty($dates)) {
            $record['openricx:hasDateRangeSet'] = $dates;
        }

        // Language
        $languages = $this->getLanguagesForRecord($ioId);
        if (!empty($languages)) {
            $record['rico:hasOrHadLanguage'] = $languages;
        }

        // Extent
        if (!empty($io->extent_and_medium)) {
            $record['rico:hasExtent'] = [
                '@type' => self::RICO_NS . 'Extent',
                'rico:hasExtentType' => $io->extent_and_medium,
            ];
        }

        // Repository
        $repository = $this->getRepositoryForRecord($ioId);
        if ($repository) {
            $record['rico:hasOrHadHolder'] = [
                '@id' => $this->baseUri . '/repository/' . $repository->slug,
                '@type' => self::RICO_NS . 'CorporateBody',
                'rico:name' => $repository->authorized_form_of_name,
            ];
        }

        // Access conditions
        if (!empty($io->access_conditions)) {
            $record['rico:conditionsOfAccess'] = $io->access_conditions;
        }

        // Subject links
        $subjects = $this->getSubjectsForRecord($ioId);
        if (!empty($subjects)) {
            $record['rico:hasOrHadSubject'] = $subjects;
        }

        // Creator agents
        $creators = $this->getCreatorsForRecord($ioId);
        if (!empty($creators)) {
            $record['rico:hasCreator'] = $creators;
        }

        // Digital objects (instantiations)
        $instantiations = $this->getInstantiationsForRecord($ioId);
        if (!empty($instantiations)) {
            $record['rico:hasOrHadInstantiation'] = $instantiations;
        }

        // Child records (hierarchy)
        $children = $this->getChildRecords($ioId);
        if (!empty($children) && ($options['include_children'] ?? false)) {
            $record['rico:includesOrIncluded'] = $children;
        }

        return $record;
    }

    /**
     * Serialize an Actor (Agent) to RIC-O JSON-LD with ISAAR compliance
     */
    public function serializeAgent(int $actorId, array $options = []): array
    {
        $culture = app()->getLocale() ?: 'en';
        $actor = DB::table('actor as a')
            ->leftJoin('actor_i18n as i18n', function ($j) use ($culture) {
                $j->on('a.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as et_i18n', function ($j) use ($culture) {
                $j->on('a.entity_type_id', '=', 'et_i18n.id')->where('et_i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'a.id', '=', 'slug.object_id')
            ->where('a.id', $actorId)
            ->select('a.*', 'i18n.*', 'et_i18n.name as entity_type_name', 'slug.slug')
            ->first();

        if (!$actor) {
            return ['error' => 'Actor not found'];
        }

        $typeKey = strtolower($actor->entity_type_name ?? '');
        $ricType = $this->actorTypeToRic[$typeKey] ?? 'Agent';

        $agent = [
            '@context' => $this->ricoContext(),
            '@id' => $this->baseUri . '/actor/' . ($actor->slug ?: $actor->id),
            '@type' => 'rico:' . $ricType,
        ];

        // ISAAR mandatory: Authorized Form of Name
        if (!empty($actor->authorized_form_of_name)) {
            $agent['rico:name'] = $actor->authorized_form_of_name;
            $agent['openricx:normalizedForm'] = $actor->authorized_form_of_name;
        }

        // ISAAR: Parallel Forms
        if (!empty($actor->parallel_form_of_name)) {
            $agent['openricx:alternativeForm'] = $actor->parallel_form_of_name;
        }

        // ISAAR: Other Forms
        if (!empty($actor->other_form_of_name)) {
            $agent['openricx:otherName'] = $actor->other_form_of_name;
        }

        // Dates
        if (!empty($actor->dates_of_existence)) {
            $agent['rico:hasBeginningDate'] = $actor->dates_of_existence;
        }

        // History
        if (!empty($actor->history)) {
            $agent['rico:history'] = $actor->history;
        }

        // Places
        $places = $this->getPlacesForActor($actorId);
        if (!empty($places)) {
            $agent['rico:isAssociatedWithPlace'] = $places;
        }

        // Mandates
        $mandates = $this->getMandatesForActor($actorId);
        if (!empty($mandates)) {
            $agent['rico:authorizingMandate'] = $mandates;
        }

        // Functions
        $functions = $this->getFunctionsForActor($actorId);
        if (!empty($functions)) {
            $agent['rico:performsOrPerformed'] = $functions;
        }

        // Occupation
        if (!empty($actor->occupation)) {
            $agent['openricx:hasOccupation'] = $actor->occupation;
        }

        // Contact
        $contact = $this->getContactInfo($actorId);
        if ($contact) {
            $agent['openricx:contact'] = $contact;
        }

        return $agent;
    }

    /**
     * Serialize a Function to RIC-O JSON-LD with ISDF compliance
     */
    public function serializeFunction(int $functionId, array $options = []): array
    {
        $function = DB::table('function_object as f')
            ->leftJoin('function_object_i18n as i18n', 'f.id', '=', 'i18n.id')
            ->where('f.id', $functionId)
            ->first();

        if (!$function) {
            return ['error' => 'Function not found'];
        }

        $ricFunc = [
            '@context' => $this->ricoContext(),
            '@id' => $this->baseUri . '/function/' . $function->id,
            '@type' => 'rico:Function',
        ];

        // ISDF: Name
        if (!empty($function->authorized_form_of_name)) {
            $ricFunc['rico:name'] = $function->authorized_form_of_name;
        }

        // ISDF: Description
        if (!empty($function->description)) {
            $ricFunc['openricx:description'] = $function->description;
        }

        // ISDF: Dates
        if (!empty($function->dates)) {
            $ricFunc['openricx:hasDateRangeSet'] = [
                '@type' => 'rico:DateRange',
                'rico:beginningDate' => $function->dates,
            ];
        }

        // ISDF: Activities carried out under this function
        $activities = $this->getActivitiesForFunction($functionId);
        if (!empty($activities)) {
            $ricFunc['rico:hasActivity'] = $activities;
        }

        // ISDF: Agents who perform this function
        $agents = $this->getAgentsForFunction($functionId);
        if (!empty($agents)) {
            $ricFunc['rico:isPerformedBy'] = $agents;
        }

        return $ricFunc;
    }

    /**
     * Serialize a Repository (Corporate Body acting as archival holder) to
     * RIC-O JSON-LD with ISDIAH compliance.
     *
     * Repositories live in the actor table with a sibling row in repository
     * (ISDIAH-specific fields). The @type is rico:CorporateBody per RiC-O 1.1
     * (rico:Repository does not exist; the institution-as-archival-holder is
     * a CorporateBody that has holdings).
     */
    public function serializeRepository(int $repositoryId, array $options = []): array
    {
        $culture = app()->getLocale() ?: 'en';
        $repo = DB::table('actor as a')
            ->leftJoin('actor_i18n as i18n', function ($j) use ($culture) {
                $j->on('a.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('repository_i18n as repo_i18n', function ($j) use ($culture) {
                $j->on('a.id', '=', 'repo_i18n.id')->where('repo_i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'a.id', '=', 'slug.object_id')
            ->where('a.id', $repositoryId)
            ->select('a.*', 'i18n.*', 'repo_i18n.*', 'slug.slug')
            ->first();

        if (!$repo) {
            return ['error' => 'Repository not found'];
        }

        $ricRepo = [
            '@context' => $this->ricoContext(),
            '@id' => $this->baseUri . '/repository/' . ($repo->slug ?: $repo->id),
            '@type' => 'rico:CorporateBody',
        ];

        // ISDIAH: Authorized Form of Name
        if (!empty($repo->authorized_form_of_name)) {
            $ricRepo['rico:name'] = $repo->authorized_form_of_name;
            $ricRepo['openricx:normalizedForm'] = $repo->authorized_form_of_name;
        }

        // ISDIAH: Contact Information
        $contact = $this->getContactInfo($repositoryId);
        if ($contact) {
            $ricRepo['openricx:contact'] = $contact;
        }

        // ISDIAH: Conditions of Access
        if (!empty($repo->access_conditions)) {
            $ricRepo['rico:conditionsOfAccess'] = $repo->access_conditions;
        }

        // ISDIAH: Holdings (fonds + collections the repository holds)
        $holdings = $this->getHoldingsForRepository($repositoryId);
        if (!empty($holdings)) {
            $ricRepo['rico:isOrWasHolderOf'] = $holdings;
        }

        return $ricRepo;
    }

    /**
     * Serialize a RiC-native Place to RIC-O JSON-LD.
     */
    public function serializePlace(int $placeId, array $options = []): array
    {
        $culture = app()->getLocale() ?: 'en';

        $place = DB::table('ric_place as p')
            ->leftJoin('ric_place_i18n as i18n', function ($j) use ($culture) {
                $j->on('p.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('ric_place as parent', 'p.parent_id', '=', 'parent.id')
            ->leftJoin('ric_place_i18n as parent_i18n', function ($j) use ($culture) {
                $j->on('parent.id', '=', 'parent_i18n.id')
                  ->where('parent_i18n.culture', '=', $culture);
            })
            ->where('p.id', $placeId)
            ->select([
                'p.*',
                'i18n.name',
                'i18n.description',
                'i18n.address',
                'parent.id as parent_place_id',
                'parent_i18n.name as parent_name',
            ])
            ->first();

        if (!$place) {
            return ['error' => 'Place not found'];
        }

        $ricPlace = [
            '@context' => $this->ricoContext(),
            '@id' => $this->baseUri . '/place/' . $place->id,
            '@type' => 'rico:Place',
        ];

        if (!empty($place->name)) {
            $ricPlace['rico:name'] = $place->name;
        }

        if (!empty($place->description)) {
            $ricPlace['openricx:description'] = $place->description;
        }

        if (!empty($place->address)) {
            $ricPlace['openricx:streetAddress'] = $place->address;
        }

        if (!empty($place->type_id)) {
            $ricPlace['openric:localType'] = $place->type_id;
        }

        if ($place->latitude !== null && $place->longitude !== null) {
            $ricPlace['rico:latitude'] = (float) $place->latitude;
            $ricPlace['rico:longitude'] = (float) $place->longitude;
        }

        if (!empty($place->authority_uri)) {
            $ricPlace['owl:sameAs'] = $place->authority_uri;
        }

        if ($place->parent_place_id) {
            $ricPlace['rico:isOrWasPartOf'] = [
                '@id' => $this->baseUri . '/place/' . $place->parent_place_id,
                '@type' => 'rico:Place',
                'rico:name' => $place->parent_name,
            ];
        }

        return $ricPlace;
    }

    /**
     * Serialize a RiC-native Rule (mandate, law, policy) to RIC-O JSON-LD.
     */
    public function serializeRule(int $ruleId, array $options = []): array
    {
        $culture = app()->getLocale() ?: 'en';

        $rule = DB::table('ric_rule as r')
            ->leftJoin('ric_rule_i18n as i18n', function ($j) use ($culture) {
                $j->on('r.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->where('r.id', $ruleId)
            ->select([
                'r.*',
                'i18n.title',
                'i18n.description',
                'i18n.legislation',
                'i18n.sources',
            ])
            ->first();

        if (!$rule) {
            return ['error' => 'Rule not found'];
        }

        $ricRule = [
            '@context' => $this->ricoContext(),
            '@id' => $this->baseUri . '/rule/' . $rule->id,
            '@type' => 'rico:Rule',
        ];

        if (!empty($rule->title)) {
            $ricRule['rico:title'] = $rule->title;
            $ricRule['rico:name'] = $rule->title;
        }

        if (!empty($rule->description)) {
            $ricRule['openricx:description'] = $rule->description;
        }

        if (!empty($rule->type_id)) {
            $ricRule['rico:hasOrHadRuleType'] = $rule->type_id;
            $ricRule['openric:localType'] = $rule->type_id;
        }

        if (!empty($rule->jurisdiction)) {
            $ricRule['openric:jurisdiction'] = $rule->jurisdiction;
        }

        if (!empty($rule->legislation)) {
            $ricRule['openricx:descriptiveNote'] = $rule->legislation;
        }

        if (!empty($rule->sources)) {
            $ricRule['dcterms:source'] = $rule->sources;
        }

        if (!empty($rule->authority_uri)) {
            $ricRule['owl:sameAs'] = $rule->authority_uri;
        }

        if ($rule->start_date || $rule->end_date) {
            $dateRange = ['@type' => 'openricx:DateRange'];
            if ($rule->start_date) {
                $dateRange['rico:beginningDate'] = [
                    '@value' => $rule->start_date,
                    '@type' => 'xsd:date',
                ];
            }
            if ($rule->end_date) {
                $dateRange['rico:endDate'] = [
                    '@value' => $rule->end_date,
                    '@type' => 'xsd:date',
                ];
            }
            $ricRule['openricx:hasDateRangeSet'] = $dateRange;
        }

        return $ricRule;
    }

    /**
     * Serialize a RiC-native Activity to RIC-O JSON-LD.
     *
     * Activity.type_id is mapped per mapping spec §6.5:
     *   production / creation / contribution → rico:Production
     *   accumulation / collection            → rico:Accumulation
     *   anything else                        → rico:Activity
     */
    public function serializeActivity(int $activityId, array $options = []): array
    {
        $culture = app()->getLocale() ?: 'en';

        $act = DB::table('ric_activity as a')
            ->leftJoin('ric_activity_i18n as i18n', function ($j) use ($culture) {
                $j->on('a.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('ric_place as p', 'a.place_id', '=', 'p.id')
            ->leftJoin('ric_place_i18n as p_i18n', function ($j) use ($culture) {
                $j->on('p.id', '=', 'p_i18n.id')->where('p_i18n.culture', '=', $culture);
            })
            ->where('a.id', $activityId)
            ->select([
                'a.*',
                'i18n.name',
                'i18n.description',
                'i18n.date_display',
                'p.id as place_ric_id',
                'p_i18n.name as place_name',
            ])
            ->first();

        if (!$act) {
            return ['error' => 'Activity not found'];
        }

        // Per spec v0.37 §6.5: every event is rico:Activity. Kind is carried in
        // rico:hasActivityType (IRI from <https://openric.org/vocab/activity-type/>).
        $typeKey = strtolower($act->type_id ?? '');
        $activityTypeIri = $this->activityTypeIri($typeKey);

        $ricAct = [
            '@context' => $this->ricoContext(),
            '@id' => $this->baseUri . '/activity/' . $act->id,
            '@type' => 'rico:Activity',
        ];

        if ($activityTypeIri) {
            $ricAct['rico:hasActivityType'] = ['@id' => $activityTypeIri];
        }

        if (!empty($act->name)) {
            $ricAct['rico:name'] = $act->name;
        }

        if (!empty($act->description)) {
            $ricAct['openricx:description'] = $act->description;
        }

        if (!empty($act->type_id)) {
            $ricAct['openric:localType'] = $act->type_id;
        }

        if ($act->start_date || $act->end_date || !empty($act->date_display)) {
            $dateRange = ['@type' => 'openricx:DateRange'];
            if ($act->start_date) {
                $dateRange['rico:beginningDate'] = [
                    '@value' => $act->start_date,
                    '@type' => 'xsd:date',
                ];
            }
            if ($act->end_date) {
                $dateRange['rico:endDate'] = [
                    '@value' => $act->end_date,
                    '@type' => 'xsd:date',
                ];
            }
            if (!empty($act->date_display)) {
                $dateRange['rico:expressedDate'] = $act->date_display;
            }
            $ricAct['rico:isAssociatedWithDate'] = $dateRange;
        }

        if ($act->place_ric_id) {
            $ricAct['rico:hasOrHadLocation'] = [
                '@id' => $this->baseUri . '/place/' . $act->place_ric_id,
                '@type' => 'rico:Place',
                'rico:name' => $act->place_name,
            ];
        }

        // rico:resultsOrResultedIn — records this activity produced.
        // Relation: Activity (subject) -> Record (object) via dropdown_code='results_from'.
        // Emitted as stubs; Provenance & Event profile requires this on rico:Production
        // and rico:Accumulation (openric-spec provenance-event.md §3.1/§3.2).
        $results = DB::table('relation as rel')
            ->join('ric_relation_meta as rm', 'rel.id', '=', 'rm.relation_id')
            ->join('information_object as io', 'rel.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as io_i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'io_i18n.id')->where('io_i18n.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as lvl_i18n', function ($j) use ($culture) {
                $j->on('io.level_of_description_id', '=', 'lvl_i18n.id')->where('lvl_i18n.culture', '=', $culture);
            })
            ->leftJoin('slug as io_slug', 'io.id', '=', 'io_slug.object_id')
            ->where('rel.subject_id', $activityId)
            ->where('rm.dropdown_code', 'results_from')
            ->select(['io.id', 'io_i18n.title', 'lvl_i18n.name as level', 'io_slug.slug'])
            ->get();

        if ($results->isNotEmpty()) {
            $ricAct['rico:resultsOrResultedIn'] = $results->map(fn($r) => [
                '@id'        => $this->baseUri . '/informationobject/' . ($r->slug ?: $r->id),
                '@type'      => 'rico:' . ($this->levelToRic[strtolower($r->level ?? '')] ?? 'Record'),
                'rico:title' => $r->title,
            ])->values()->toArray();
        }

        // rico:hasOrHadParticipant — agents who performed this activity.
        // Relation: Activity (subject) -> Agent (object) via dropdown_code='performed_by'.
        // Backing data uses rico:isOrWasPerformedBy (RiC-O subproperty of hasOrHadParticipant);
        // the serializer emits the broader profile-level predicate the spec mandates.
        //
        // When the relation carries a role (ric_relation_meta.role_term_id — the
        // capacity in which the agent participated, e.g. "bride"), we ALSO emit a
        // reified rico:EventRelation node qualified with openricx:relationHasAgentRole
        // -> rico:RoleType. This is the RiC-O EventRelation pattern (Clavaud, ICA/EGAD,
        // Records_in_Contexts_users list 2026-06); openricx:relationHasAgentRole is the
        // generic OpenRiC extension property pending its RiC-O 1.2 equivalent
        // (see tools/openric_ext.ttl). The flat shortcut and the reified node coexist,
        // mirroring RiC-O's shortcut + full-relation duality.
        $participants = DB::table('relation as rel')
            ->join('ric_relation_meta as rm', 'rel.id', '=', 'rm.relation_id')
            ->join('actor as ac', 'rel.object_id', '=', 'ac.id')
            ->leftJoin('actor_i18n as ac_i18n', function ($j) use ($culture) {
                $j->on('ac.id', '=', 'ac_i18n.id')->where('ac_i18n.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as role_i18n', function ($j) use ($culture) {
                $j->on('rm.role_term_id', '=', 'role_i18n.id')->where('role_i18n.culture', '=', $culture);
            })
            ->where('rel.subject_id', $activityId)
            ->where('rm.dropdown_code', 'performed_by')
            ->select([
                'rel.id as relation_id',
                'ac.id',
                'ac.entity_type_id',
                'ac_i18n.authorized_form_of_name as name',
                'rm.role_term_id',
                'role_i18n.name as role_label',
            ])
            ->get();

        if ($participants->isNotEmpty()) {
            $ricAct['rico:hasOrHadParticipant'] = $participants->map(fn($a) => [
                '@id'       => $this->baseUri . '/actor/' . $a->id,
                '@type'     => 'rico:' . ($this->actorTypeToRic[strtolower($a->entity_type_id ?? '')] ?? 'Agent'),
                'rico:name' => $a->name,
            ])->values()->toArray();

            // Reified EventRelation(s) — only for participants whose role is recorded.
            $roled = $participants->filter(fn($a) => $a->role_term_id !== null && $a->role_label !== null);
            if ($roled->isNotEmpty()) {
                $ricAct['rico:thingIsSourceOfRelation'] = $roled->map(fn($a) => [
                    '@id'   => $this->baseUri . '/relation/event/' . $a->relation_id,
                    '@type' => 'rico:EventRelation',
                    'rico:relationHasTarget' => [
                        '@id'       => $this->baseUri . '/actor/' . $a->id,
                        '@type'     => 'rico:' . ($this->actorTypeToRic[strtolower($a->entity_type_id ?? '')] ?? 'Agent'),
                        'rico:name' => $a->name,
                    ],
                    'openricx:relationHasAgentRole' => [
                        '@id'       => $this->baseUri . '/roletype/' . $a->role_term_id,
                        '@type'     => 'rico:RoleType',
                        'rico:name' => $a->role_label,
                    ],
                ])->values()->toArray();
            }
        }

        return $ricAct;
    }

    /**
     * Serialize a RiC-native Instantiation (digital or physical manifestation) to RIC-O JSON-LD.
     */
    public function serializeInstantiation(int $instantiationId, array $options = []): array
    {
        $culture = app()->getLocale() ?: 'en';

        $inst = DB::table('ric_instantiation as ri')
            ->leftJoin('ric_instantiation_i18n as i18n', function ($j) use ($culture) {
                $j->on('ri.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('information_object as io', 'ri.record_id', '=', 'io.id')
            ->leftJoin('slug as io_slug', 'io.id', '=', 'io_slug.object_id')
            ->leftJoin('information_object_i18n as io_i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'io_i18n.id')->where('io_i18n.culture', '=', $culture);
            })
            ->where('ri.id', $instantiationId)
            ->select([
                'ri.*',
                'i18n.title',
                'i18n.description',
                'i18n.technical_characteristics',
                'i18n.production_technical_characteristics',
                'io_slug.slug as record_slug',
                'io_i18n.title as record_title',
            ])
            ->first();

        if (!$inst) {
            return ['error' => 'Instantiation not found'];
        }

        $ricInst = [
            '@context' => $this->ricoContext(),
            '@id' => $this->baseUri . '/instantiation/' . $inst->id,
            '@type' => 'rico:Instantiation',
        ];

        if (!empty($inst->title)) {
            $ricInst['rico:identifier'] = $inst->title;
            $ricInst['rico:title'] = $inst->title;
        }

        if (!empty($inst->description)) {
            $ricInst['openricx:description'] = $inst->description;
        }

        if (!empty($inst->mime_type)) {
            $ricInst['openricx:hasMimeType'] = $inst->mime_type;
        }

        if (!empty($inst->carrier_type)) {
            $ricInst['rico:hasCarrierType'] = $inst->carrier_type;
        }

        // openricx:hasCarrier — the individual physical carrier object (e.g. the
        // magnetic tape itself), distinct from its carrier TYPE. RiC-O 1.1 has no
        // Carrier entity; openricx:Carrier (subclass of rico:Thing) fills the gap
        // pending RiC-O 2.0's Carrier class (Clavaud, ICA/EGAD, list 2026-07). One
        // carrier may bear several instantiations, so it is a shared, identified node.
        if (!empty($inst->carrier_identifier)) {
            $carrier = [
                '@id'             => $this->baseUri . '/carrier/' . rawurlencode($inst->carrier_identifier),
                '@type'           => 'openricx:Carrier',
                'rico:identifier' => $inst->carrier_identifier,
            ];
            if (!empty($inst->carrier_type)) {
                $carrier['rico:hasCarrierType'] = $inst->carrier_type;
            }
            $ricInst['openricx:hasCarrier'] = $carrier;
        }

        if ($inst->extent_value !== null) {
            $ricInst['rico:hasExtent'] = [
                '@type' => 'rico:Extent',
                'rico:quantity' => (float) $inst->extent_value,
                'rico:hasExtentType' => $inst->extent_unit ?: 'bytes',
            ];
        }

        if (!empty($inst->technical_characteristics)) {
            $ricInst['openricx:technicalCharacteristics'] = $inst->technical_characteristics;
        }

        if (!empty($inst->production_technical_characteristics)) {
            $ricInst['openricx:productionTechnicalCharacteristics'] =
                $inst->production_technical_characteristics;
        }

        if ($inst->record_id && $inst->record_slug) {
            $ricInst['rico:isOrWasInstantiationOf'] = [
                '@id' => $this->baseUri . '/informationobject/' . $inst->record_slug,
                '@type' => 'rico:Record',
                'rico:title' => $inst->record_title,
            ];
        }

        return $ricInst;
    }

    /**
     * Serialize with ISCAP compliance (Security/Access)
     */
    public function addIscapCompliance(array $ricEntity, int $entityId, string $entityType): array
    {
        $culture = app()->getLocale() ?: 'en';

        $security = DB::table('security_access_condition_link as sacl')
            ->join('security_level as sl', 'sacl.classification_id', '=', 'sl.id')
            ->leftJoin('security_level_i18n as sl_i18n', function ($j) use ($culture) {
                $j->on('sl.id', '=', 'sl_i18n.id')->where('sl_i18n.culture', '=', $culture);
            })
            ->where('sacl.object_id', $entityId)
            ->select('sl_i18n.name', 'sl.classification', 'sl.level_value')
            ->first();

        if ($security) {
            // Per spec v0.37 §9: rico:SecurityClassification is not a RiC-O 1.1 class.
            // Canonical pattern: rico:Rule + rico:hasOrHadRuleType <vocab/security-classification>.
            $ricEntity['rico:isOrWasRegulatedBy'] = [
                '@type' => 'rico:Rule',
                'rico:hasOrHadRuleType' => ['@id' => 'https://openric.org/vocab/rule-type/security-classification'],
                'openricx:securityLevel' => $security->name,
                'openricx:securityLevelCode' => $security->classification,
            ];
        }

        // Access Restriction
        $restrictions = $this->getAccessRestrictions($entityType, $entityId);
        if (!empty($restrictions)) {
            $ricEntity['rico:isOrWasRegulatedBy'] = $restrictions;
        }

        // Personal Data
        $hasPersonalData = $this->checkPersonalData($entityType, $entityId);
        if ($hasPersonalData) {
            $ricEntity['openricx:containsPersonalData'] = true;
        }

        return $ricEntity;
    }

    /**
     * Export entire RecordSet (Fonds/Collection) as a JSON-LD @graph
     * containing the root entity plus all descendants.
     */
    public function exportRecordSet(int $fondsId, array $options = []): array
    {
        $fonds = $this->serializeRecord($fondsId, $options);
        if (isset($fonds['error'])) {
            return $fonds;
        }

        $descendants = $this->getAllDescendants($fondsId);

        return [
            '@context' => $this->ricoContext(),
            '@graph'   => array_merge([$fonds], $descendants),
        ];
    }

    // =========================================================================
    // Private helpers — DB queries that build the nested JSON-LD nodes
    // referenced by the public serialize* methods above.
    // =========================================================================

    /**
     * Get dates for a record (event rows attached to the information object).
     * Returns a rico:DateRangeSet node, or null when there are no events.
     */
    private function getDatesForRecord(int $ioId): ?array
    {
        $dates = DB::table('event')
            ->leftJoin('event_i18n', function ($j) {
                $j->on('event.id', '=', 'event_i18n.id')
                   ->where('event_i18n.culture', '=', 'en');
            })
            ->where('event.object_id', $ioId)
            ->select('event.id', 'event.type_id', 'event.start_date', 'event.end_date', 'event_i18n.date as date_display')
            ->get();

        if ($dates->isEmpty()) {
            return null;
        }

        $dateRanges = [];
        foreach ($dates as $date) {
            $dateRanges[] = [
                '@type' => 'rico:DateRange',
                'rico:beginningDate' => $date->start_date ?? null,
                'rico:endDate' => $date->end_date ?? null,
                'rico:expressedDate' => $date->date_display ?? null,
                'openric:localType' => $date->type_id ?? 'existence',
            ];
        }

        return [
            '@type' => 'rico:DateRangeSet',
            'rico:hasDateRange' => $dateRanges,
        ];
    }

    /**
     * Get languages for a record from object_term_relation (taxonomy 7 = language).
     */
    private function getLanguagesForRecord(int $ioId): array
    {
        return DB::table('object_term_relation')
            ->join('term_i18n', function ($j) {
                $j->on('object_term_relation.term_id', '=', 'term_i18n.id')
                   ->where('term_i18n.culture', '=', 'en');
            })
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $ioId)
            ->where('term.taxonomy_id', 7)
            ->pluck('term_i18n.name')
            ->map(fn($lang) => [
                '@type' => 'rico:Language',
                'rico:languageCode' => $lang,
            ])
            ->toArray();
    }

    /**
     * Get the repository (holder) for a record. Returns the raw row with slug
     * and authorized form of name — caller composes the JSON-LD node.
     */
    private function getRepositoryForRecord(int $ioId): ?object
    {
        return DB::table('repository as r')
            ->leftJoin('actor_i18n as i18n', function ($j) {
                $j->on('r.id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
            })
            ->leftJoin('slug', 'r.id', '=', 'slug.object_id')
            ->join('information_object', 'information_object.repository_id', '=', 'r.id')
            ->where('information_object.id', $ioId)
            ->select('r.*', 'i18n.authorized_form_of_name', 'slug.slug')
            ->first();
    }

    /**
     * Get subjects for a record from object_term_relation (taxonomy 35 = subject).
     */
    private function getSubjectsForRecord(int $ioId): array
    {
        return DB::table('object_term_relation as otr')
            ->join('term as t', 'otr.term_id', '=', 't.id')
            ->join('term_i18n as ti', 't.id', '=', 'ti.id')
            ->where('otr.object_id', $ioId)
            ->where('t.taxonomy_id', 35)
            ->where('ti.culture', 'en')
            ->pluck('ti.name')
            ->map(fn($name) => [
                '@type' => 'skos:Concept',
                'skos:prefLabel' => $name,
            ])
            ->toArray();
    }

    /**
     * Get creator agents for a record. Reads event rows with
     * type_id = EVENT_TYPE_CREATION joined to the actor table.
     */
    private function getCreatorsForRecord(int $ioId): array
    {
        return DB::table('event')
            ->join('actor as a', 'event.actor_id', '=', 'a.id')
            ->join('actor_i18n as i18n', function ($j) {
                $j->on('a.id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
            })
            ->where('event.object_id', $ioId)
            ->where('event.type_id', TermId::EVENT_TYPE_CREATION)
            ->whereNotNull('event.actor_id')
            ->select('a.id', 'i18n.authorized_form_of_name', 'a.entity_type_id')
            ->distinct()
            ->get()
            ->map(fn($actor) => [
                '@id' => $this->baseUri . '/actor/' . $actor->id,
                '@type' => 'rico:' . ($this->actorTypeToRic[strtolower($actor->entity_type_id ?? '')] ?? 'Agent'),
                'rico:name' => $actor->authorized_form_of_name,
            ])
            ->toArray();
    }

    /**
     * Get instantiations (digital objects) for a record.
     */
    private function getInstantiationsForRecord(int $ioId): array
    {
        return DB::table('digital_object as do')
            ->where('do.object_id', $ioId)
            ->get()
            ->map(fn($do) => [
                '@type' => 'rico:Instantiation',
                'rico:identifier' => $do->name,
                'openricx:hasMimeType' => $do->mime_type ?? null,
                'rico:hasExtent' => [
                    '@type' => 'rico:Extent',
                    'rico:quantity' => isset($do->byte_size) ? (float) $do->byte_size : null,
                    'rico:hasExtentType' => 'bytes',
                ],
            ])
            ->toArray();
    }

    /**
     * Get immediate child records under a parent information_object.
     */
    private function getChildRecords(int $parentId): array
    {
        return DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', 'io.id', '=', 'i18n.id')
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.parent_id', $parentId)
            ->select('io.id', 'slug.slug', 'i18n.title', 'io.identifier')
            ->get()
            ->map(fn($child) => [
                '@id' => $this->baseUri . '/informationobject/' . ($child->slug ?? $child->id),
                '@type' => 'rico:RecordPart',
                'rico:identifier' => $child->identifier,
                'rico:title' => $child->title,
            ])
            ->toArray();
    }

    /**
     * Recursively walk the description tree under $parentId and return a flat
     * list of fully-serialized child records. Capped at depth 10 to guard
     * against cyclic parent_id loops in legacy AtoM data.
     */
    private function getAllDescendants(int $parentId, int $depth = 0): array
    {
        if ($depth > 10) {
            return [];
        }

        $children = $this->getChildRecords($parentId);
        $allDescendants = [];

        foreach ($children as $child) {
            $childId = $this->extractIdFromUri($child['@id']);
            $allDescendants[] = $this->serializeRecord($childId, ['include_children' => false]);
            $allDescendants = array_merge($allDescendants, $this->getAllDescendants($childId, $depth + 1));
        }

        return $allDescendants;
    }

    /**
     * Get places associated with an actor. AtoM stores actor-places as a
     * free-text field on actor_i18n; we emit a single node from it.
     */
    private function getPlacesForActor(int $actorId): array
    {
        $placesText = DB::table('actor_i18n')
            ->where('id', $actorId)
            ->where('culture', 'en')
            ->value('places');

        if (empty($placesText)) {
            return [];
        }

        return [
            [
                '@type' => 'rico:Place',
                'rico:name' => strip_tags($placesText),
            ],
        ];
    }

    /**
     * Get mandates for an actor (free-text field on actor_i18n, plus optional
     * structured rows in the mandate table).
     */
    private function getMandatesForActor(int $actorId): array
    {
        $mandateText = DB::table('actor_i18n')
            ->where('id', $actorId)
            ->where('culture', 'en')
            ->value('mandates');

        if (empty($mandateText)) {
            $structured = DB::table('mandate')
                ->where('actor_id', $actorId)
                ->get();
            if ($structured->isEmpty()) {
                return [];
            }
            return $structured->map(fn($m) => [
                '@type' => 'rico:Mandate',
                'openricx:description' => $m->description ?? null,
            ])->toArray();
        }

        return [
            [
                '@type' => 'rico:Mandate',
                'openricx:description' => strip_tags($mandateText),
            ],
        ];
    }

    /**
     * Get the functions an actor performs (via relation table, type_id 40).
     */
    private function getFunctionsForActor(int $actorId): array
    {
        return DB::table('relation as r')
            ->join('function_object as f', 'r.object_id', '=', 'f.id')
            ->join('function_object_i18n as fi', 'f.id', '=', 'fi.id')
            ->where('r.subject_id', $actorId)
            ->where('r.type_id', 40)
            ->select('f.id', 'fi.authorized_form_of_name')
            ->get()
            ->map(fn($func) => [
                '@id' => $this->baseUri . '/function/' . $func->id,
                '@type' => 'rico:Function',
                'rico:name' => $func->authorized_form_of_name,
            ])
            ->toArray();
    }

    /**
     * Get structured rico:Occupation nodes for an actor.
     * Schema::hasTable guard lets the serializer survive a half-installed
     * instance where ric_occupation model code is deployed but the
     * migration hasn't run yet.
     */
    private function getOccupationsForActor(int $actorId): array
    {
        if (!Schema::hasTable('ric_occupation')) {
            return [];
        }

        $rows = DB::table('ric_occupation')
            ->where('actor_id', $actorId)
            ->orderByDesc('is_current')
            ->orderByDesc('start_date')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $node = [
                '@id' => $this->baseUri . '/occupation/' . $row->id,
                '@type' => 'rico:Occupation',
                'rdfs:label' => $row->title,
            ];
            if (!empty($row->start_date)) {
                $node['rico:beginningDate'] = $row->start_date;
            }
            if (!empty($row->end_date)) {
                $node['rico:endDate'] = $row->end_date;
            }
            if (!empty($row->description)) {
                $node['openricx:descriptiveNote'] = $row->description;
            }
            if (!empty($row->is_current)) {
                $node['openricx:isCurrent'] = true;
            }
            $out[] = $node;
        }

        return $out;
    }

    /**
     * Get contact info for an agent or repository.
     * RiC-O 1.1 uses rico:ContactPoint (not rico:Contact — pre-1.1).
     */
    private function getContactInfo(int $actorId): ?array
    {
        $contact = DB::table('contact_information')
            ->where('actor_id', $actorId)
            ->first();

        if (!$contact) {
            return null;
        }

        return [
            '@type' => 'rico:ContactPoint',
            'openricx:streetAddress' => $contact->street_address ?? null,
            'openricx:postalCode' => $contact->postal_code ?? null,
            'openricx:city' => $contact->city ?? null,
            'openricx:country' => $contact->country ?? null,
            'openricx:telephone' => $contact->telephone ?? null,
            'openricx:email' => $contact->email ?? null,
        ];
    }

    /**
     * Get activities (events) carried out under a function.
     *
     * Why: OpenRiC's ric_activity table has no function_id column — the
     * function ↔ activity link lives in the relation table (like every other
     * RiC association). Until that mapping is wired here, returning [] is
     * correct: serializeFunction emits no rico:hasActivity rather than 500ing.
     */
    private function getActivitiesForFunction(int $functionId): array
    {
        if (!Schema::hasColumn('ric_activity', 'function_id')) {
            return [];
        }
        return DB::table('ric_activity')
            ->where('function_id', $functionId)
            ->get()
            ->map(fn($act) => [
                '@type' => 'rico:Activity',
                'openricx:description' => $act->description ?? null,
            ])
            ->toArray();
    }

    /**
     * Get agents that perform a function (via relation table, type_id 40).
     */
    private function getAgentsForFunction(int $functionId): array
    {
        return DB::table('relation as r')
            ->join('actor as a', 'r.object_id', '=', 'a.id')
            ->join('actor_i18n as i18n', 'a.id', '=', 'i18n.id')
            ->where('r.subject_id', $functionId)
            ->where('r.type_id', 40)
            ->select('a.id', 'i18n.authorized_form_of_name')
            ->get()
            ->map(fn($agent) => [
                '@id' => $this->baseUri . '/actor/' . $agent->id,
                '@type' => 'rico:Agent',
                'rico:name' => $agent->authorized_form_of_name,
            ])
            ->toArray();
    }

    /**
     * Get top-level holdings (fonds / collections) for a repository.
     * Capped at 100 to keep the repository JSON-LD payload bounded.
     */
    private function getHoldingsForRepository(int $repositoryId): array
    {
        $holdings = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', 'io.id', '=', 'i18n.id')
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->leftJoin('term as level', 'io.level_of_description_id', '=', 'level.id')
            ->leftJoin('term_i18n as level_i18n', 'level.id', '=', 'level_i18n.id')
            ->where('io.repository_id', $repositoryId)
            ->whereIn('level_i18n.name', ['fonds', 'collection'])
            ->select('io.id', 'slug.slug', 'i18n.title', 'level_i18n.name as level')
            ->limit(100)
            ->get();

        return $holdings->map(fn($h) => [
            '@id' => $this->baseUri . '/informationobject/' . ($h->slug ?? $h->id),
            '@type' => 'rico:RecordSet',
            'rico:title' => $h->title,
        ])->toArray();
    }

    /**
     * Get ICIP access restrictions for an information object.
     * Returns [] for other entity types (no access-restriction table).
     * Schema::hasTable guard for instances without the ICIP migrations.
     */
    private function getAccessRestrictions(string $entityType, int $entityId): array
    {
        if ($entityType !== 'information_object') {
            return [];
        }
        if (!Schema::hasTable('icip_access_restriction')) {
            return [];
        }

        $rows = DB::table('icip_access_restriction')
            ->where('information_object_id', $entityId)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $entry = [
                '@type' => 'rico:Rule',
                'rico:hasOrHadRuleType' => ['@id' => 'https://openric.org/vocab/rule-type/access-restriction'],
                'openricx:restrictionType' => $r->restriction_type,
            ];
            if (!empty($r->custom_restriction_text)) {
                $entry['openricx:customText'] = $r->custom_restriction_text;
            }
            if (!empty($r->start_date)) {
                $entry['openricx:startDate'] = $r->start_date;
            }
            if (!empty($r->end_date)) {
                $entry['openricx:endDate'] = $r->end_date;
            }
            $entry['openricx:appliesToDescendants'] = (bool) ($r->applies_to_descendants ?? false);
            $out[] = $entry;
        }
        return $out;
    }

    /**
     * Check whether an entity has personal data attached (personal_data_log
     * keyed by object.id, regardless of CTI entity type).
     */
    private function checkPersonalData(string $entityType, int $entityId): bool
    {
        if (!Schema::hasTable('personal_data_log')) {
            return false;
        }
        return DB::table('personal_data_log')
            ->where('object_id', $entityId)
            ->exists();
    }

    /**
     * Extract the trailing numeric/slug segment from a baseUri-prefixed URI.
     * Used by getAllDescendants to recurse off @id values produced by
     * getChildRecords.
     */
    private function extractIdFromUri(string $uri): int
    {
        $parts = explode('/', $uri);
        return (int) end($parts);
    }
}
