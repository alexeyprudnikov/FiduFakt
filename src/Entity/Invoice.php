<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Controller\InvoicePayController;
use App\Enum\InvoiceStatus;
use App\Repository\InvoiceRepository;
use App\State\InvoiceDownloadProvider;
use App\State\InvoiceGenerateProcessor;
use App\State\InvoicePayProcessor;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JsonException;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ApiResource(
    operations: [
        new Get(
            security: "is_granted('INVOICE_VIEW', object)",
            securityMessage: "Dies ist nicht Ihre Rechnung."
        ),
        new Get(
            uriTemplate: '/info'
        ),
        new GetCollection(),
        new Post(
            uriTemplate: '/invoice/generate',
            openapi: new OpenApiOperation(
                summary: 'Rechnung generieren',
                description: 'Erstellt eine neue E-Rechnung aus JSON-Daten.'
            ),
            name: 'api_invoice_generate',
            processor: InvoiceGenerateProcessor::class,
        ),
        new Get(
            uriTemplate: '/invoice/{id}/download',
            openapi: new OpenApiOperation(
                summary: 'Lädt die archivierte oder generierte ZUGFeRD-Rechnung herunter'
            ),
            name: 'api_invoice_download',
            provider: InvoiceDownloadProvider::class
        ),
        new Patch(
            uriTemplate: '/invoice/{id}/pay',
            inputFormats: ['json' => ['application/json']],
            name: 'api_invoice_pay',
            processor: InvoicePayProcessor::class
        )
    ],
    normalizationContext: ['groups' => ['invoice:read']],
    denormalizationContext: ['groups' => ['invoice:write']]
)]
class Invoice
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[Groups(['invoice:read'])]
    private(set) Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "invoices")]
    #[ORM\JoinColumn(nullable: false)]
    private(set) ?User $user = null;

    #[ORM\Column(length: 50)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[ApiProperty(openapiContext: ['example' => 'RE-2024-001'])]
    public string $invoiceNumber {
        set => trim($value);
    }

    #[ORM\Column(type: 'string', enumType: InvoiceStatus::class)]
    #[Groups(['invoice:read'])]
    public InvoiceStatus $status = InvoiceStatus::DRAFT;

    #[ORM\Column]
    #[Groups(['invoice:read'])]
    private(set) DateTimeImmutable $createdAt;

    #[ORM\Column]
    #[Groups(['invoice:read'])]
    private(set) DateTimeImmutable $dueDate;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['invoice:read'])]
    private(set) ?string $pdfPath = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Groups(['invoice:read'])]
    private(set) ?string $payloadHash = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Groups(['invoice:read'])]
    private(set) ?string $fileHash = null;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[ApiProperty(openapiContext: ['example' => [
        'creditor' => [
            'account' => [
                'iban' => 'DE12345678901234567890'
            ],
            'institution' => [
                'bic' => 'ABCDEFF1XXX'
            ]
        ],
        'customer' => [
            'name' => 'Max Mustermann',
            'addressLineOne' => 'Teststrasse 12',
            'postCode' => '12345',
            'city' => 'Berlin',
            'countryCode' => 'DE',
            'email' => 'max.mustermann@test-email.de',
            'phone' => '+491791234567',
            'vatId' => '122/34/56'
        ],
        'items' => [
            [
                'description' => 'KI Beratung',
                'quantity' => 2,
                'price' => '150.00',
            ],
            [
                'description' => 'Software Lizenz',
                'quantity' => 1,
                'price' => '500.00',
            ]
        ]
    ]])]
    public array $rawPayload = [];

    /**
     * @throws DateMalformedStringException
     */
    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new DateTimeImmutable();
        $this->dueDate = $this->createdAt->modify('+14 days');
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @throws JsonException
     */
    public function securePayload(): self
    {
        $this->payloadHash = hash('sha256', json_encode($this->rawPayload, JSON_THROW_ON_ERROR));
        return $this;
    }

    public function setFileHash(string $hash): self
    {
        $this->fileHash = $hash;
        return $this;
    }

    public function markAsArchived(string $path): self
    {
        $this->pdfPath = $path;
        return $this;
    }
}
