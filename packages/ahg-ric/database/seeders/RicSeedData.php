<?php

/**
 * RicSeedData - Sample RIC-O Records for Testing
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

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RicSeedData extends Seeder
{
    /**
     * Sample RIC-O Agent (Person)
     */
    public function samplePerson(): array
    {
        return [
            '@context' => [
                'rico' => 'https://www.ica.org/standards/RiC/ontology#',
                'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            ],
            '@id' => 'http://example.org/agent/person-001',
            '@type' => 'rico:Person',
            'rico:identifier' => 'PER-001',
            'rico:name' => 'Jane Smith',
            'openricx:agentType' => 'Person',
            'openricx:hasDate' => [
                '@type' => 'openricx:DateRange',
                'rico:normalizedDateValue' => '1850-1920',
            ],
            'openricx:hasFunction' => [
                '@id' => 'http://example.org/function/func-001',
            ],
        ];
    }

    /**
     * Sample RIC-O Agent (Corporate Body)
     */
    public function sampleCorporateBody(): array
    {
        return [
            '@context' => [
                'rico' => 'https://www.ica.org/standards/RiC/ontology#',
            ],
            '@id' => 'http://example.org/agent/org-001',
            '@type' => 'rico:CorporateBody',
            'rico:identifier' => 'ORG-001',
            'rico:name' => 'South African National Archives',
            'openricx:agentType' => 'Corporate Body',
            'rico:hasOrHadLocation' => 'Pretoria, South Africa',
            'rico:authorizingMandate' => [
                '@type' => 'rico:Mandate',
                'openricx:text' => 'National Archives Act No. 43 of 1992',
            ],
        ];
    }

    /**
     * Sample RIC-O Record (RecordSet/Fonds)
     */
    public function sampleRecordSet(): array
    {
        return [
            '@context' => [
                'rico' => 'https://www.ica.org/standards/RiC/ontology#',
            ],
            '@id' => 'http://example.org/record/fonds-001',
            '@type' => 'rico:RecordSet',
            'rico:identifier' => 'FONDS-001',
            'rico:title' => 'Government Archives: Department of Education',
            'rico:hasRecordSetType' => 'fonds',
            'openricx:hasDate' => [
                '@type' => 'openricx:DateRange',
                'rico:normalizedDateValue' => '1910-1994',
            ],
            'rico:hasOrHadLanguage' => [
                ['rico:name' => 'English'],
                ['rico:name' => 'Afrikaans'],
            ],
            'openricx:hasAccumulation' => 'Approximately 500 linear meters of records',
            'openricx:hasRecordResourceRelation' => [
                '@type' => 'openricx:RecordResourceRelation',
                'openricx:relationType' => 'creator',
                // Per spec v0.37.0: rico:agent is not a RiC-O 1.1 property.
                // Use a typed Agent linking property; for creator role, rico:hasCreator is canonical.
                'rico:hasCreator' => ['@id' => 'http://example.org/agent/org-001'],
            ],
        ];
    }

    /**
     * Sample RIC-O Record (Item)
     */
    public function sampleRecord(): array
    {
        return [
            '@context' => [
                'rico' => 'https://www.ica.org/standards/RiC/ontology#',
            ],
            '@id' => 'http://example.org/record/item-001',
            '@type' => 'rico:Record',
            'rico:identifier' => 'ED-001-001',
            'rico:title' => 'Annual Report of the Department of Education, 1920',
            'openricx:hasRecordType' => 'document',
            'openricx:hasDate' => [
                '@type' => 'rico:Date',
                'rico:normalizedDateValue' => '1920',
            ],
            'rico:hasOrHadInstantiation' => [
                [
                    '@type' => 'rico:Instantiation',
                    'openricx:hasInstantiationType' => 'digital',
                    'rico:identifier' => 'DIG-001',
                    'openricx:hasMimeType' => 'application/pdf',
                ],
            ],
        ];
    }

    /**
     * Sample RIC-O Function
     */
    public function sampleFunction(): array
    {
        return [
            '@context' => [
                'rico' => 'https://www.ica.org/standards/RiC/ontology#',
            ],
            '@id' => 'http://example.org/function/func-001',
            '@type' => 'openricx:Function',
            'rico:identifier' => 'FUNC-001',
            'rico:name' => 'Education Policy Development',
            'openricx:hasFunctionType' => 'policy',
            'openricx:hasDate' => [
                '@type' => 'openricx:DateRange',
                'rico:normalizedDateValue' => '1910-1994',
            ],
            'rico:performsOrPerformed' => [
                '@id' => 'http://example.org/agent/org-001',
            ],
        ];
    }

    /**
     * Sample RIC-O Repository (ISDIAH)
     */
    public function sampleRepository(): array
    {
        return [
            '@context' => [
                'rico' => 'https://www.ica.org/standards/RiC/ontology#',
            ],
            '@id' => 'http://example.org/repository/repo-001',
            '@type' => 'openricx:Repository',
            'rico:identifier' => 'REPO-001',
            'rico:name' => 'National Archives of South Africa',
            'rico:hasOrHadLocation' => [
                '@type' => 'rico:Place',
                'rico:name' => 'Private Bag X236, Pretoria, 0001',
            ],
            'openricx:contact' => [
                'openricx:email' => 'archives@national.gov.za',
                'openricx:telephone' => '+27 12 441 3200',
            ],
            'rico:isOrWasRegulatedBy' => 'Open to public with restrictions',
        ];
    }

    /**
     * Run the seed data.
     */
    public function run(): void
    {
        $samples = [
            'Person' => $this->samplePerson(),
            'CorporateBody' => $this->sampleCorporateBody(),
            'RecordSet' => $this->sampleRecordSet(),
            'Record' => $this->sampleRecord(),
            'Function' => $this->sampleFunction(),
            'Repository' => $this->sampleRepository(),
        ];

        foreach ($samples as $type => $data) {
            $this->command->info("Sample {$type}:");
            $this->command->line(json_encode($data, JSON_PRETTY_PRINT));
            $this->command->newLine();
        }
    }
}
