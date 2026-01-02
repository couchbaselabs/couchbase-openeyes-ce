<?php
/**
 * Console command to run relationship analysis
 * 
 * Usage: php protected/yiic.php analyzerelationships
 */

class AnalyzerelationshipsCommand extends CConsoleCommand
{
    public function run($args)
    {
        require_once(Yii::getPathOfAlias('application.scripts.couchbase.analyzers') . '/RelationshipAnalyzer.php');
        
        $analyzer = new RelationshipAnalyzer();
        $outputPath = Yii::getPathOfAlias('application.scripts.couchbase.analyzers') . '/relationship-map.json';
        $analyzer->exportToJson($outputPath);
        
        echo "Analysis complete!\n";
    }
}
