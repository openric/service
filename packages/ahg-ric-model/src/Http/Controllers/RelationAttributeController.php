<?php

declare(strict_types=1);

namespace AhgRicModel\Http\Controllers;

use AhgRicModel\Http\Controllers\Concerns\HandlesFusekiFailure;
use AhgRicModel\Services\OntologyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RelationAttributeController
{
    use HandlesFusekiFailure;

    public function __construct(private readonly OntologyService $ontology)
    {
    }

    public function index(string $version): Response|\Illuminate\Contracts\View\View
    {
        return $this->guard(function () use ($version) {
            return view('ahg-ric-model::relation-attributes.index', [
                'version'            => $version,
                'relationAttributes' => $this->ontology->listRelationAttributes($version),
                'attribution' => config('ahg-ric-model.attribution'),
            ]);
        });
    }

    public function show(string $version, string $id): Response|\Illuminate\Contracts\View\View
    {
        return $this->guard(function () use ($version, $id) {
            $relationAttribute = $this->ontology->getRelationAttribute($id, $version);
            if ($relationAttribute === null) {
                throw new NotFoundHttpException("Relation attribute '{$id}' not found in RiC-CM {$version}.");
            }
            return view('ahg-ric-model::relation-attributes.show', [
                'version'           => $version,
                'relationAttribute' => $relationAttribute,
                'attribution' => config('ahg-ric-model.attribution'),
            ]);
        });
    }

    public function redirectToLatest(Request $request, ?string $id = null): RedirectResponse
    {
        $latest = $this->ontology->latestVersion();
        $target = $id !== null
            ? route('reference.ric-cm.relation-attributes.show', ['version' => $latest, 'id' => $id])
            : route('reference.ric-cm.relation-attributes.index', ['version' => $latest]);
        return redirect($target, 302);
    }
}
