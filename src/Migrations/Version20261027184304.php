<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027184304 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Players (link accounts), tafels results and Messenger queue';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE player (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(30) NOT NULL, token_hash VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_98197A65B3BC57DA (token_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tafels_result (id INT AUTO_INCREMENT NOT NULL, table_number SMALLINT NOT NULL, duration_ms INT NOT NULL, passed TINYINT NOT NULL, completed_at DATETIME NOT NULL, player_id INT NOT NULL, INDEX tafels_result_player_table (player_id, table_number), INDEX IDX_A6DCFE9599E6F5DF (player_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE tafels_result ADD CONSTRAINT FK_A6DCFE9599E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tafels_result DROP FOREIGN KEY FK_A6DCFE9599E6F5DF');
        $this->addSql('DROP TABLE player');
        $this->addSql('DROP TABLE tafels_result');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
