<?php

declare(strict_types=1);

namespace Yaup\Repository;

final readonly class Repository
{
    public function __construct(
        public string $name,
        public string $path,
        public ?string $remote,
    ) {}

    /** @return array{name: string, path: string, remote?: string} */
    public function toArray(): array
    {
        $result = ['name' => $this->name, 'path' => $this->path];
        if (null !== $this->remote) {
            $result['remote'] = $this->remote;
        }

        return $result;
    }
}
