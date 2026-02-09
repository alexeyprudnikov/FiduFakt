<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Subscription
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private(set) ?int $id = null;

    #[ORM\Column]
    private(set) DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'subscription')]
        #[ORM\JoinColumn(nullable: false)]
        public User $user,

        #[ORM\Column(length: 255)]
        public string $stripeSubscriptionId,

        #[ORM\Column]
        public int $invoiceLimit = 50,

        #[ORM\Column]
        public string $planName = 'starter'
    ) {
        $this->createdAt = new DateTimeImmutable();
    }
}
