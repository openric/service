{{--
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za
  SPDX-License-Identifier: AGPL-3.0-or-later

  Task-first entry into the RiC-CM reference.

  The navigator is a strong formal reference, but it asks a first-time
  archivist to pick an entity class before they have said what they hold. This
  block inverts that: describe the thing, land on the entity. The formal
  reference underneath is unchanged - this is a layer on top, not a
  replacement.

  Entity ids are the real RiC-CM ones (RiC-E01..RiC-E22); the set is stable
  within a version, and an unknown id would 404, so do not invent them.
--}}

@php
    $tasks = [
        ['thing' => 'A fonds, a collection or a series',   'entity' => 'RiC-E03', 'name' => 'Record Set',      'why' => 'Anything that groups records rather than being one.'],
        ['thing' => 'One record',                          'entity' => 'RiC-E04', 'name' => 'Record',          'why' => 'A letter, a report, a photograph, a recording - a thing described in its own right.'],
        ['thing' => 'Part of a record',                    'entity' => 'RiC-E05', 'name' => 'Record Part',     'why' => 'A component with no independent existence, like one letter in a bound volume.'],
        ['thing' => 'A person',                            'entity' => 'RiC-E08', 'name' => 'Person',          'why' => 'An individual human, living or dead.'],
        ['thing' => 'An organisation',                     'entity' => 'RiC-E11', 'name' => 'Corporate Body',  'why' => 'A company, department, church, society - an organised group acting as one.'],
        ['thing' => 'A family',                            'entity' => 'RiC-E10', 'name' => 'Family',          'why' => 'Related people treated as a single creator or subject.'],
        ['thing' => 'Something that happened',             'entity' => 'RiC-E15', 'name' => 'Activity',        'why' => 'Creation, accumulation, custody, transfer - a specific occurrence.'],
        ['thing' => 'A place',                             'entity' => 'RiC-E22', 'name' => 'Place',           'why' => 'Where something was created, held or is about.'],
        ['thing' => 'A date or a span of time',            'entity' => 'RiC-E18', 'name' => 'Date',            'why' => 'A point or range, including uncertain and approximate ones.'],
        ['thing' => 'A policy, law or mandate',            'entity' => 'RiC-E16', 'name' => 'Rule',            'why' => 'What authorised or constrained the records. See also Mandate.'],
        ['thing' => 'A physical or digital copy',          'entity' => 'RiC-E06', 'name' => 'Instantiation',   'why' => 'The manifestation, not the intellectual record. One record, many instantiations.'],
    ];

    $comparisons = [
        ['a' => 'Record',      'aId' => 'RiC-E04', 'b' => 'Record Set',     'bId' => 'RiC-E03', 'note' => 'A set groups; a record is described in its own right. A file of correspondence is a set whose members are records.'],
        ['a' => 'Record',      'aId' => 'RiC-E04', 'b' => 'Record Part',    'bId' => 'RiC-E05', 'note' => 'A part has no independent existence. A single letter inside a bound volume is a part, not a record.'],
        ['a' => 'Record',      'aId' => 'RiC-E04', 'b' => 'Instantiation',  'bId' => 'RiC-E06', 'note' => 'The record is the intellectual thing; the instantiation is a physical or digital manifestation of it.'],
        ['a' => 'Agent',       'aId' => 'RiC-E07', 'b' => 'Corporate Body', 'bId' => 'RiC-E11', 'note' => 'Agent is the umbrella. Person, Family, Group and Corporate Body are its kinds - describe the kind you actually have.'],
        ['a' => 'Activity',    'aId' => 'RiC-E15', 'b' => 'Event',          'bId' => 'RiC-E14', 'note' => 'An activity is carried out by an agent; an event may simply have occurred. A flood is an event, an appraisal is an activity.'],
    ];
@endphp

<div class="card subtle-card mb-4">
    <div class="card-body">
        <h2 class="h5 mb-1">What are you trying to describe?</h2>
        <p class="text-muted small mb-3">Start from what you hold rather than from the class list. Each one opens the formal reference for that entity.</p>

        <div class="row g-2">
            @foreach ($tasks as $t)
                <div class="col-12 col-md-6 col-lg-4">
                    <a class="d-block p-2 rounded text-decoration-none task-entry"
                       href="{{ route('reference.ric-cm.entities.show', ['version' => $version, 'id' => $t['entity']]) }}">
                        <div class="fw-semibold">{{ $t['thing'] }}</div>
                        <div class="small text-muted">{{ $t['why'] }}</div>
                        <div class="small mt-1">
                            <span class="text-body">{{ $t['name'] }}</span>
                            <code class="ric-id">{{ $t['entity'] }}</code>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <p class="small text-muted mt-3 mb-0">
            Not sure which fits? The
            <a href="https://openric.org/wizard/" target="_blank" rel="noopener">modelling wizard</a>
            works through a real scenario with you and shows its reasoning.
        </p>
    </div>
</div>

<div class="card subtle-card mb-4">
    <div class="card-body">
        <h2 class="h5 mb-1">Commonly confused</h2>
        <p class="text-muted small mb-3">These five distinctions cost the most time. Each is a real modelling decision, not a naming preference.</p>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <tbody>
                    @foreach ($comparisons as $c)
                        <tr>
                            <td class="text-nowrap">
                                <a href="{{ route('reference.ric-cm.entities.show', ['version' => $version, 'id' => $c['aId']]) }}">{{ $c['a'] }}</a>
                                <span class="text-muted">vs</span>
                                <a href="{{ route('reference.ric-cm.entities.show', ['version' => $version, 'id' => $c['bId']]) }}">{{ $c['b'] }}</a>
                            </td>
                            <td class="small text-muted">{{ $c['note'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
