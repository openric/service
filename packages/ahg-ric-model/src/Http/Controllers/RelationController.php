<?php

declare(strict_types=1);

namespace AhgRicModel\Http\Controllers;

use AhgRicModel\Http\Controllers\Concerns\HandlesFusekiFailure;
use AhgRicModel\Services\OntologyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RelationController
{
    use HandlesFusekiFailure;

    public function __construct(private readonly OntologyService $ontology)
    {
    }

    public function index(string $version): Response|\Illuminate\Contracts\View\View
    {
        return $this->guard(function () use ($version) {
            return view('ahg-ric-model::relations.index', [
                'version'   => $version,
                'relations' => $this->ontology->listRelations($version),
                'attribution' => config('ahg-ric-model.attribution'),
            ]);
        });
    }

    public function show(string $version, string $id): Response|\Illuminate\Contracts\View\View
    {
        return $this->guard(function () use ($version, $id) {
            $relation = $this->ontology->getRelation($id, $version);
            if ($relation === null) {
                throw new NotFoundHttpException("Relation '{$id}' not found in RiC-CM {$version}.");
            }
            return view('ahg-ric-model::relations.show', [
                'version'     => $version,
                'relation'    => $relation,
                'attribution' => config('ahg-ric-model.attribution'),
            ]);
        });
    }

    public function redirectToLatest(Request $request, ?string $id = null): RedirectResponse
    {
        $latest = $this->ontology->latestVersion();
        $target = $id !== null
            ? route('reference.ric-cm.relations.show', ['version' => $latest, 'id' => $id])
            : route('reference.ric-cm.relations.index', ['version' => $latest]);
        return redirect($target, 302);
    }
}
