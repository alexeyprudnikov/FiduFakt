<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use App\Enum\InvoiceStatus;
use App\Repository\InvoiceRepository;
use App\State\InfoProvider;
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
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Uid\Uuid;
use ArrayObject;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/info',
            openapi: new OpenApiOperation(
                responses: [
                    '200' => new OpenApiResponse(
                        content: new ArrayObject([
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => ['type' => 'string'],
                                        'user' => ['type' => 'string']
                                    ]
                                ],
                                'example' => [
                                    'message' => 'Willkommen bei FiduFakt',
                                    'user' => 'max.musterman@test.de',
                                ]
                            ]
                        ])
                    )
                ],
                summary: 'Info.',
                description: 'API Informationen ansehen.'
            ),
            name: 'api_info',
            provider: InfoProvider::class
        ),
        new Get(
            openapi: new OpenApiOperation(
                summary: 'Rechnung.',
                description: 'Rechnung ansehen.'
            ),
            security: "is_granted('INVOICE_VIEW', object)",
            securityMessage: "Dies ist nicht Ihre Rechnung."
        ),
        new GetCollection(
            // todo: show only own invoices
            openapi: new OpenApiOperation(
                summary: 'Alle Rechnungen.',
                description: 'Alle Rechnungen ansehen.'
            ),
        ),
        new Post(
            uriTemplate: '/invoice/generate',
            openapi: new OpenApiOperation(
                summary: 'Rechnung generieren.',
                description: 'Erstellt eine neue E-Rechnung aus JSON-Daten.'
            ),
            name: 'api_invoice_generate',
            processor: InvoiceGenerateProcessor::class,
        ),
        new Get(
            uriTemplate: '/invoice/{id}/download',
            openapi: new OpenApiOperation(
                summary: 'Rechnung herunterladen.',
                description: 'Lädt die archivierte oder generierte ZUGFeRD-Rechnung herunter.'
            ),
            name: 'api_invoice_download',
            provider: InvoiceDownloadProvider::class
        ),
        new Patch(
            uriTemplate: '/invoice/{id}/pay',
            inputFormats: ['json' => ['application/json']],
            openapi: new OpenApiOperation(
                summary: 'Rechnung bezahlt.',
                description: 'Rechnung als bezahlt markieren.'
            ),
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
            'email' => 'max.mustermann@test.de',
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

    #[Groups(['invoice:read'])]
    #[SerializedName('totalAmount')]
    public function getTotalAmount(): float
    {
        $total = 0.0;
        if (isset($this->rawPayload['items']) && is_array($this->rawPayload['items'])) {
            foreach ($this->rawPayload['items'] as $item) {
                $lineTotal = (float)($item['price'] ?? 0) * (float)($item['quantity'] ?? 1);
                $total += $lineTotal;
            }
        }

        return $total;
    }
}
