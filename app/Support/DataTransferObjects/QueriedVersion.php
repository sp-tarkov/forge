<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

/**
 * A resolved mod or addon version from a dependency query, paired with the queried identifier:version pair strings
 * that matched it.
 */
final readonly class QueriedVersion
{
    /**
     * @param  list<string>  $pairKeys
     */
    public function __construct(
        public int $versionId,
        public array $pairKeys,
    ) {}
}
