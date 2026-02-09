<?php

namespace App\Command;

use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reset-quota',
    description: 'Setzt den monatlichen Rechnungs-Zähler für alle User zurück',
)]
class ResetMonthlyQuotaCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $today = new DateTime();

        $currentDay = (int)$today->format('d');
        $isLastDayOfMonth = $currentDay === (int)$today->format('t');

        $users = $this->userRepository->findAll();
        $counter = 0;

        foreach ($users as $user) {
            $subscription = $user->getSubscription();
            if ($subscription) {
                $startDay = (int)$subscription->getCreatedAt()->format('d');
                if ($startDay === $currentDay || ($isLastDayOfMonth && $startDay > $currentDay)) {
                    $user->resetInvoiceCount();
                    $counter++;
                    $io->note(sprintf('Reset für User %s (Abo-Tag: %s)', $user->email, $startDay));
                }
            }
        }

        $this->em->flush();

        if ($counter > 0) {
            $io->success(sprintf('%d Kontingente wurden heute am individuellen Stichtag zurückgesetzt.', $counter));
        } else {
            $io->info('Heute stehen keine Kontingent-Resets an.');
        }

        return Command::SUCCESS;
    }
}
