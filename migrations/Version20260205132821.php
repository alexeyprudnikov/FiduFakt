<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260205132821 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_c912ed9d5f37a13b');
        $this->addSql('ALTER TABLE api_key ADD token_hash VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE api_key ADD prefix VARCHAR(12) NOT NULL');
        $this->addSql('ALTER TABLE api_key DROP token');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE api_key ADD token VARCHAR(64) NOT NULL');
        $this->addSql('ALTER TABLE api_key DROP token_hash');
        $this->addSql('ALTER TABLE api_key DROP prefix');
        $this->addSql('CREATE UNIQUE INDEX uniq_c912ed9d5f37a13b ON api_key (token)');
    }
}
