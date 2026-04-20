<?php

declare(strict_types=1);

namespace AhgRicModel\Http\Controllers;

use AhgRicModel\Http\Controllers\Concerns\HandlesFusekiFailure;
use AhgRicModel\Services\OntologyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AttributeController
{
    use HandlesFusekiFailure;

    public function __construct(private readonly OntologyService $ontology)
    {
    }

    public function index(string $version): Response|\Illuminate\Contracts\View\View
    {
        return $this->guard(function () use ($version) {
            return view('ahg-ric-model::attributes.index', [
                'version'    => $version,
                'attributes' => $this->ontology->listAttributes($version),
                'attribution' => config('ahg-ric-model.attribution'),
            ]);
        });
    }

    public function show(string $version, string $id): Response|\Illuminate\Contracts\View\View
    {
        return $this->guard(function () use ($version, $id) {
            $attribute = $this->ontology->getAttribute($id, $version);
            if ($attribute === null) {
                throw new NotFoundHttpException("Attribute '{$id}' not found in RiC-CM {$version}.");
            }
            return view('ahg-ric-model::attributes.show', [
                'version'     => $version,
                'attribute'   => $attribute,
                'attribution' => config('ahg-ric-model.attribution'),
            ]);
        });
    }

    public function redirectToLatest(Request $request, ?string $id = null): RedirectResponse
    {
        $latest = $this->ontology->latestVersion();
        $target = $id !== null
            ? route('reference.ric-cm.attributes.show', ['version' => $latest, 'id' => $id])
            : route('reference.ric-cm.attributes.index', ['version' => $latest]);
        return redirect($target, 302);
    }
}
