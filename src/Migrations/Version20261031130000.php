<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261031130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Practice time: seconds played per player and day';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE practice_day (id INT AUTO_INCREMENT NOT NULL, day DATE NOT NULL, seconds INT NOT NULL, player_id INT NOT NULL, UNIQUE INDEX practice_day_player_day (player_id, day), INDEX IDX_C213826599E6F5DF (player_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE practice_day ADD CONSTRAINT FK_C213826599E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE practice_day DROP FOREIGN KEY FK_C213826599E6F5DF');
        $this->addSql('DROP TABLE practice_day');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
