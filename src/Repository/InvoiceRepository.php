<?php

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\User;
use App\Enum\InvoiceStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function findLatestByUser(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.user = :user')
            ->setParameter('user', $user)
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // src/Repository/InvoiceRepository.php

    public function getStatsByUser(User $user): array
    {
        // Wir holen uns alle relevanten Daten in einer Abfrage
        $data = $this->createQueryBuilder('i')
            ->select('i.status, i.rawPayload')
            ->where('i.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        $stats = [
            'open_amount' => 0.0,
            'open_count' => 0,
            'paid_amount' => 0.0
        ];

        foreach ($data as $row) {
            $total = 0.0;
            if (isset($row['rawPayload']['items'])) {
                foreach ($row['rawPayload']['items'] as $item) {
                    $total += ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
                }
            }

            if ($row['status']->value === InvoiceStatus::PAID->value) {
                $stats['paid_amount'] += $total;
            } else {
                $stats['open_amount'] += $total;
                $stats['open_count']++;
            }
        }

        return $stats;
    }
}
