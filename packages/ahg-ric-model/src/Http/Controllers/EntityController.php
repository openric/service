<?php

declare(strict_types=1);

namespace AhgRicModel\Http\Controllers;

use AhgRicModel\Http\Controllers\Concerns\HandlesFusekiFailure;
use AhgRicModel\Services\OntologyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EntityController
{
    use HandlesFusekiFailure;

    public function __construct(private readonly OntologyService $ontology)
    {
    }

    public function index(string $version): Response|\Illuminate\Contracts\View\View
    {
        return $this->guard(function () use ($version) {
            return view('ahg-ric-model::entities.index', [
                'version'  => $version,
                'entities' => $this->ontology->listEntities($version),
                'attribution' => config('ahg-ric-model.attribution'),
            ]);
        });
    }

    public function show(string $version, string $id): Response|\Illuminate\Contracts\View\View
    {
        return $this->guard(function () use ($version, $id) {
            $data = $this->ontology->getEntity($id, $version);
            if ($data === null) {
                throw new NotFoundHttpException("Entity '{$id}' not found in RiC-CM {$version}.");
            }
            return view('ahg-ric-model::entities.show', [
                'version'     => $version,
                'entity'      => $data['entity'],
                'ancestors'   => $data['ancestors'],
                'descendants' => $data['descendants'],
                'declaredAttributes'  => $data['declaredAttributes'],
                'inheritedAttributes' => $data['inheritedAttributes'],
                'declaredRelations'   => $data['declaredRelations'],
                'inheritedRelations'  => $data['inheritedRelations'],
                'attribution' => config('ahg-ric-model.attribution'),
            ]);
        });
    }

    public function redirectToLatest(Request $request, ?string $id = null): RedirectResponse
    {
        $latest = $this->ontology->latestVersion();
        $target = $id !== null
            ? route('reference.ric-cm.entities.show', ['version' => $latest, 'id' => $id])
            : route('reference.ric-cm.entities.index', ['version' => $latest]);
        return redirect($target, 302);
    }
}
