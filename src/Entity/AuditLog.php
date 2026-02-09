<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use DateTimeImmutable;

#[ORM\Entity]
class AuditLog
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private(set) ?int $id = null;

    #[ORM\Column]
    private(set) DateTimeImmutable $occurredAt;

    public function __construct(
        #[ORM\Column(length: 255)]
        public string $action,

        #[ORM\Column(length: 255, nullable: true)]
        public ?string $userIdentifier,

        #[ORM\Column(type: 'json')]
        public array $context = [],

        #[ORM\Column(length: 45, nullable: true)]
        public ?string $ipAddress = null
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }
}
