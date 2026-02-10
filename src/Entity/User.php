<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: 'Es existiert bereits ein Konto mit dieser E-Mail-Adresse.')]
class User implements UserInterface
{
    /**
     * default value for free tier
     */
    public const int INVOICE_COUNT_DEFAULT = 20;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private(set) ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    public string $email;

    #[ORM\Column]
    public array $roles = [];

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $stripeCustomerId = null;

    #[ORM\Column]
    private(set) int $invoiceCount = 0;

    /** @var Collection<int, Invoice> */
    #[
        ORM\OneToMany(
            targetEntity: Invoice::class,
            mappedBy: "user",
            orphanRemoval: true,
        ),
    ]
    #[ORM\OrderBy(["createdAt" => "DESC"])]
    public Collection $invoices;

    #[ORM\OneToOne(targetEntity: Subscription::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    public ?Subscription $subscription = null;

    /** @var Collection<int, ApiKey> */
    #[ORM\OneToMany(targetEntity: ApiKey::class, mappedBy: "user", orphanRemoval: true)]
    public Collection $apiKeys;

    public function __construct()
    {
        $this->invoices = new ArrayCollection();
        $this->apiKeys = new ArrayCollection();
    }

    public function getUserIdentifier(): string { return $this->email; }
    public function getRoles(): array { return array_unique([...$this->roles, 'ROLE_USER']); }

    public function incrementInvoiceCount(): void
    {
        $this->invoiceCount++;
    }

    public function resetInvoiceCount(): void
    {
        $this->invoiceCount = 0;
    }

    /**
     * Prüft, ob der User noch Rechnungen erstellen darf.
     */
    public function hasQuotaLeft(): bool
    {
        return $this->invoiceCount < ($this->subscription?->invoiceLimit ?? self::INVOICE_COUNT_DEFAULT);
    }

    public function getKeyLimit(): int
    {
        return ($this->subscription === null || $this->subscription->planName === 'starter') ? 1 : 5;
    }

    public function isKeyLimitReached(): bool
    {
        return $this->apiKeys->count() >= $this->getKeyLimit();
    }
}
