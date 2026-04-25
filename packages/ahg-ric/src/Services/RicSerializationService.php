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
            ->leftJoin('term_i18n as level_i18n', 'level.id', '=', 'level_i18n.id')
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

        $ricType = $this->levelToRic[$io->level_name] ?? 'Record';

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
            '@id' => $this->baseUri . '/repository/' . ($repo->slug ?: $repo->id),
            '@type' => 'rico:CorporateBody',
        ];

        // ISDIAH: Authorized Form
        if (!empty($repo->authorized_form_of_name)) {
            $ricRepo['rico:name'] = $repo->authorized_form_of_name;
        }

        // ISDIAH: Contact Information
        $contact = $this->getContactInfo($repositoryId);
        if ($contact) {
            $ricRepo['openricx:contact'] = $contact;
        }

        // ISDIAH: Access
        if (!empty($repo->access_conditions)) {
            $ricRepo['rico:conditionsOfAccess'] = $repo->access_conditions;
        }

        // ISDIAH: Holdings
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
        $participants = DB::table('relation as rel')
            ->join('ric_relation_meta as rm', 'rel.id', '=', 'rm.relation_id')
            ->join('actor as ac', 'rel.object_id', '=', 'ac.id')
            ->leftJoin('actor_i18n as ac_i18n', function ($j) use ($culture) {
                $j->on('ac.id', '=', 'ac_i18n.id')->where('ac_i18n.culture', '=', $culture);
            })
            ->where('rel.subject_id', $activityId)
            ->where('rm.dropdown_code', 'performed_by')
            ->select(['ac.id', 'ac.entity_type_id', 'ac_i18n.authorized_form_of_name as name'])
            ->get();

        if ($participants->isNotEmpty()) {
            $ricAct['rico:hasOrHadParticipant'] = $participants->map(fn($a) => [
                '@id'       => $this->baseUri . '/actor/' . $a->id,
                '@type'     => 'rico:' . ($this->actorTypeToRic[strtolower($a->entity_type_id ?? '')] ?? 'Agent'),
                'rico:name' => $a->name,
            ])->values()->toArray();
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
     * Export entire RecordSet (Fonds/Collection) as JSON-LD
     */
    public function exportRecordSet(int $fondsId, array $options = []): array
    {
        $fonds = $this->serializeRecord($fondsId, $options);
        
        // Include all descendants
        $descendants = $this->getAllDescendants($fondsId);
        
        $graph = [
            '@context' => $this->ricoContext(),
            '@id' => $this->baseUri . '/thing/' . $thingId,
            '@type' => self::RICO_NS . 'Thing',
            'rico:type' => $thing->type_id ?? 'box',
        ];

        if (!empty($thing->name)) {
            $record['rico:name'] = $thing->name;
        }
        if (!empty($thing->identifier)) {
            $record['rico:identifier'] = $thing->identifier;
        }
        if (!empty($thing->description)) {
            $record['openricx:description'] = $thing->description;
        }
        if (!empty($thing->barcode)) {
            $record['rico:identifier'] = [
                ['@type' => 'rico:Identifier', 'openricx:identifierType' => 'barcode', 'rico:textualValue' => $thing->barcode],
            ];
        }

        // Physical dimensions
        $dimensions = array_filter([
            'width' => $thing->width ?? null,
            'height' => $thing->height ?? null,
            'depth' => $thing->depth ?? null,
        ]);
        if (!empty($dimensions)) {
            $record['openricx:physicalCharacteristics'] = $dimensions;
        }

        // Capacity
        if ($thing->total_capacity) {
            $record['openricx:extent'] = [
                'openricx:totalCapacity' => (int) $thing->total_capacity,
                'openricx:usedCapacity' => (int) ($thing->used_capacity ?? 0),
                'openricx:unit' => $thing->capacity_unit ?? 'items',
            ];
        }

        // Current location (from ric_thing_location)
        $currentLocation = DB::table('ric_thing_location as rtl')
            ->join('ric_place_i18n as rpi', function ($j) use ($culture) {
                $j->on('rtl.ric_place_id', '=', 'rpi.id')->where('rpi.culture', '=', $culture);
            })
            ->where('rtl.ric_thing_id', $thingId)
            ->where('rtl.is_current', 1)
            ->select('rtl.ric_place_id', 'rpi.name as place_name', 'rtl.start_date')
            ->first();

        if ($currentLocation) {
            $record['rico:hasOrHadLocation'] = [
                '@id' => $this->baseUri . '/place/' . $currentLocation->ric_place_id,
                '@type' => self::RICO_NS . 'Place',
                'openricx:placeName' => $currentLocation->place_name,
            ];
        } elseif ($thing->building || $thing->room) {
            // Fallback to physical_object_extended location
            $locationParts = array_filter([
                $thing->building, $thing->floor ? 'Floor ' . $thing->floor : null,
                $thing->room ? 'Room ' . $thing->room : null,
                $thing->aisle ? 'Aisle ' . $thing->aisle : null,
                $thing->bay ? 'Bay ' . $thing->bay : null,
                $thing->rack ? 'Rack ' . $thing->rack : null,
                $thing->shelf ? 'Shelf ' . $thing->shelf : null,
            ]);
            if (!empty($locationParts)) {
                $record['rico:hasOrHadLocation'] = [
                    '@type' => self::RICO_NS . 'Place',
                    'openricx:placeName' => implode(' > ', $locationParts),
                ];
            }
        }

        // Contained instantiations
        $instantiations = DB::table('ric_thing_instantiation as rti2')
            ->join('ric_instantiation as ri', 'rti2.ric_instantiation_id', '=', 'ri.id')
            ->leftJoin('ric_instantiation_i18n as rii', function ($j) use ($culture) {
                $j->on('ri.id', '=', 'rii.id')->where('rii.culture', '=', $culture);
            })
            ->where('rti2.ric_thing_id', $thingId)
            ->select('ri.id', 'ri.record_id', 'rii.title', 'rti2.sequence_number')
            ->orderBy('rti2.sequence_number')
            ->get();

        if ($instantiations->isNotEmpty()) {
            $record['rico:containsOrContained'] = $instantiations->map(fn($inst) => [
                '@id' => $this->baseUri . '/instantiation/' . $inst->id,
                '@type' => self::RICO_NS . 'Instantiation',
                'rico:title' => $inst->title,
                'rico:isOrWasInstantiationOf' => $inst->record_id ? $this->baseUri . '/informationobject/' . $inst->record_id : null,
            ])->toArray();
        }

        // Parent container
        if ($thing->parent_id) {
            $record['rico:isOrWasIncludedIn'] = [
                '@id' => $this->baseUri . '/thing/' . $thing->parent_id,
                '@type' => self::RICO_NS . 'Thing',
            ];
        }

        // Environment
        if ($thing->climate_controlled) {
            $record['openricx:environmentalConditions'] = ['climateControlled' => true];
        }
        if ($thing->condition_note) {
            $record['openricx:conditionNote'] = $thing->condition_note;
        }

        $record['openricx:status'] = $thing->status ?? 'active';

        return $record;
    }
}
