<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use DateTimeImmutable;

#[ORM\Entity]
class ApiKey
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public private(set) ?int $id = null;

    /**
     * Wir speichern nur den Hash des Tokens.
     * Niemand (auch du nicht) kann den Key im Klartext aus der DB lesen.
     */
    #[ORM\Column(length: 255)]
    public string $tokenHash;

    /**
     * Der Prefix (z.B. ff_8a2b) dient zur Identifikation im Dashboard.
     */
    #[ORM\Column(length: 12)]
    public string $prefix;

    #[ORM\Column]
    public DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public ?DateTimeImmutable $lastUsedAt = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'apiKeys')]
        #[ORM\JoinColumn(nullable: false)]
        public User $user,

        // Wir übergeben den Klartext-Token nur im Konstruktor,
        // damit der Controller ihn einmalig anzeigen kann.
        string $plainToken,

        #[ORM\Column(length: 255)]
        public string $name = 'Standard Key' // Name des Keys zur Identifikation
    ) {
        $this->tokenHash = password_hash($plainToken, PASSWORD_BCRYPT);
        $this->prefix = substr($plainToken, 0, 10); // Speichert z.B. "ff_1234567"
        $this->createdAt = new DateTimeImmutable();
    }
}
