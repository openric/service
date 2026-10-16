<?php

/**
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of OpenRiC.
 *
 * OpenRiC is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgRic\Support;

/**
 * RiC-O 1.1 names for relation predicates.
 *
 * The relation-type taxonomy in the database shared with Heratio still carries
 * 25 predicate names from before RiC-O 1.1 (rico:hasInstantiation, rico:heldBy,
 * ...). The service does not change that shared data; it maps each name to its
 * RiC-O 1.1 property wherever a predicate leaves the service, and stores the
 * 1.1 name on new relations. Every target below is a property in RiC-O 1.1.
 * Direction is unchanged: "X heldBy Y" becomes "X hasOrHadHolder Y".
 */
final class RicoTerms
{
    public const LEGACY = [
        'rico:hasInstantiation'          => 'rico:hasOrHadInstantiation',
        'rico:isInstantiationOf'         => 'rico:isOrWasInstantiationOf',
        'rico:hasDerivedInstantiation'   => 'rico:hasOrHadDerivedInstantiation',
        'rico:isDerivedFromInstantiation'=> 'rico:isOrWasDerivedFromInstantiation',
        'rico:heldBy'                    => 'rico:hasOrHadHolder',
        'rico:holds'                     => 'rico:isOrWasHolderOf',
        'rico:hasPart'                   => 'rico:hasOrHadPart',
        'rico:isPartOf'                  => 'rico:isOrWasPartOf',
        'rico:includes'                  => 'rico:includesOrIncluded',
        'rico:isIncludedIn'              => 'rico:isOrWasIncludedIn',
        'rico:precedes'                  => 'rico:precedesOrPreceded',
        'rico:follows'                   => 'rico:followsOrFollowed',
        'rico:hasProvenance'             => 'rico:hasOrganicOrFunctionalProvenance',
        'rico:isProvenanceOf'            => 'rico:isOrganicOrFunctionalProvenanceOf',
        'rico:hasFamilyMember'           => 'rico:hasOrHadMember',
        'rico:isFamilyMemberOf'          => 'rico:isOrWasMemberOf',
        'rico:isParentOf'                => 'rico:hasChild',
        'rico:isSiblingOf'               => 'rico:hasSibling',
        'rico:isPredecessorOf'           => 'rico:hasSuccessor',
        'rico:isSubordinateTo'           => 'rico:isOrWasSubordinateTo',
        'rico:isSuperiorOf'              => 'rico:hasOrHadSubordinate',
        'rico:managedBy'                 => 'rico:hasOrHadManager',
        'rico:manages'                   => 'rico:isOrWasManagerOf',
        'rico:isDocumentedBy'            => 'rico:documentedBy',
        'rico:isAssociatedWith'          => 'rico:isRelatedTo',
    ];

    /** The RiC-O 1.1 name for a predicate; anything not in the map is returned as given. */
    public static function canonical(?string $predicate): ?string
    {
        return $predicate === null ? null : (self::LEGACY[$predicate] ?? $predicate);
    }

    /** Map rico_predicate and inverse_predicate on a row (object or array) in place. */
    public static function row($row)
    {
        foreach (['rico_predicate', 'inverse_predicate', 'predicate'] as $k) {
            if (is_object($row) && isset($row->$k)) {
                $row->$k = self::canonical($row->$k);
            } elseif (is_array($row) && isset($row[$k])) {
                $row[$k] = self::canonical($row[$k]);
            }
        }
        return $row;
    }
}
