<?php

declare(strict_types=1);

namespace AhgRicModel\Http\Controllers;

use AhgRicModel\Http\Controllers\Concerns\HandlesFusekiFailure;
use AhgRicModel\Services\OntologyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class ReferenceIndexController
{
    use HandlesFusekiFailure;

    public function __construct(private readonly OntologyService $ontology)
    {
    }

    public function index(string $version): Response|\Illuminate\Contracts\View\View
    {
        return $this->guard(function () use ($version) {
            return view('ahg-ric-model::index', [
                'version'            => $version,
                'availableVersions'  => $this->ontology->listAvailableVersions(),
                'latestVersion'      => $this->ontology->latestVersion(),
                'hierarchy'          => $this->ontology->getHierarchy($version),
                'counts'             => [
                    'entities'            => count($this->ontology->listEntities($version)),
                    'attributes'          => count($this->ontology->listAttributes($version)),
                    'relations'           => count($this->ontology->listRelations($version)),
                    'relationAttributes'  => count($this->ontology->listRelationAttributes($version)),
                ],
                'attribution' => config('ahg-ric-model.attribution'),
            ]);
        });
    }

    public function redirectToLatest(): RedirectResponse
    {
        return redirect()->route('reference.ric-cm.index', ['version' => $this->ontology->latestVersion()], 302);
    }
}
