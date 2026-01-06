<?php
// Simple verification that the syntax is correct
$code = <<<'PHP'
$criteria = new CDbCriteria();
$params = array();
if (isset($_GET['term']) && $term = $_GET['term']) {
    $criteria->addCondition("LOWER(term) LIKE :term OR LOWER(aliases) LIKE :term");
    $params[':term'] = '%'.strtolower(strtr($term, array('%' => '\%'))).'%';
}
$criteria->order = 'term';
$criteria->limit = '200';
if (@$_GET['code']) {
    if (@$_GET['code'] == 'systemic') {
        $criteria->addCondition('specialty_id is null');
    } else {
        $criteria->join = 'join specialty on specialty_id = specialty.id AND specialty.code = :specode';
        $params[':specode'] = $_GET['code'];
    }
}
$criteria->params = $params;
PHP;

// Check for syntax errors
$tokens = @token_get_all($code);
if ($tokens === false) {
    echo "Syntax Error";
} else {
    echo "Syntax OK";
}
?>
