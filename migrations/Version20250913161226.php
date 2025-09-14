<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250913161226 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add due_at (datetime_immutable, nullable) to todo table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE todo ADD COLUMN due_at DATETIME DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE todo DROP COLUMN due_at');
    }
}
