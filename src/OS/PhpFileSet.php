<?php

declare(strict_types=1);

namespace Mihaeu\PhpDependencies\OS;

use Mihaeu\PhpDependencies\Util\AbstractCollection;

/**
 * @extends AbstractCollection<PhpFile>
 */
class PhpFileSet extends AbstractCollection
{
    public function add(PhpFile $file): static
    {
        $clone = clone $this;
        if ($this->contains($file)) {
            return $clone;
        }

        $clone->collection[] = $file;
        return $clone;
    }

    public function addAll(PhpFileSet $otherCollection): static
    {
        return $otherCollection->reduce(clone $this, function (self $set, PhpFile $file) {
            return $set->add($file);
        });
    }

    public function contains(mixed $other): bool
    {
        return $this->any(function (PhpFile $file) use ($other) {
            return $file->equals($other);
        });
    }
}
