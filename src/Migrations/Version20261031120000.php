<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Written by hand: a schema diff would drop tafels_result and lose every result.
 */
final class Version20261031120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Results of all games: tafels_result becomes game_result (game + level)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tafels_result DROP FOREIGN KEY FK_A6DCFE9599E6F5DF');
        $this->addSql('RENAME TABLE tafels_result TO game_result');
        $this->addSql("ALTER TABLE game_result ADD game VARCHAR(16) NOT NULL DEFAULT 'tafels' AFTER id, CHANGE table_number level SMALLINT NOT NULL");
        $this->addSql('ALTER TABLE game_result ALTER game DROP DEFAULT');
        $this->addSql('DROP INDEX tafels_result_player_table ON game_result');
        $this->addSql('CREATE INDEX game_result_player_game_level ON game_result (player_id, game, level)');
        // Drop + create instead of RENAME INDEX, which older MySQL/MariaDB versions lack.
        $this->addSql('DROP INDEX IDX_A6DCFE9599E6F5DF ON game_result');
        $this->addSql('CREATE INDEX IDX_6E5F6CDB99E6F5DF ON game_result (player_id)');
        $this->addSql('ALTER TABLE game_result ADD CONSTRAINT FK_6E5F6CDB99E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_result DROP FOREIGN KEY FK_6E5F6CDB99E6F5DF');
        $this->addSql("DELETE FROM game_result WHERE game <> 'tafels'");
        $this->addSql('DROP INDEX game_result_player_game_level ON game_result');
        $this->addSql('ALTER TABLE game_result DROP game, CHANGE level table_number SMALLINT NOT NULL');
        $this->addSql('RENAME TABLE game_result TO tafels_result');
        $this->addSql('CREATE INDEX tafels_result_player_table ON tafels_result (player_id, table_number)');
        $this->addSql('DROP INDEX IDX_6E5F6CDB99E6F5DF ON tafels_result');
        $this->addSql('CREATE INDEX IDX_A6DCFE9599E6F5DF ON tafels_result (player_id)');
        $this->addSql('ALTER TABLE tafels_result ADD CONSTRAINT FK_A6DCFE9599E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE');
    }
}
