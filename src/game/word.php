<?php
/**
 * @author Stan Gumeniuk i@vigo.su
 */

const TREE_REVERT_POSTFIX_KEY = 'treeRevertPostFixNew10|';
const TREE_KEY = 'tree|';
function game_word_getAlphabet()
{
    return [
        'а',
        'б',
        'в',
        'г',
        'д',
        'е',
        'ж',
        'з',
        'и',
        'й',
        'к',
        'л',
        'м',
        'н',
        'о',
        'п',
        'р',
        'с',
        'т',
        'у',
        'ф',
        'х',
        'ц',
        'ч',
        'ш',
        'щ',
        'ъ',
        'ы',
        'э',
        'ю',
        'я',
    ];
}

function game_word_wordAdd($word)
{
    $word = trim($word);

    $mckey = 'tree|';

    $l = mb_strlen($word);

    $time = 0;

    for ($key = 0; $key < $l; $key++) {

        $letter = mb_substr($word, (int) $key, 1, 'UTF8');

        if ($key == 0) {
            $cmckeyparent = $mckey . 'root';
        } else {
            $cmckeyparent = $mckey . mb_substr($word, 0, (int) $key, 'UTF8');
        }

        $cmkeycurrent = $mckey . mb_substr($word, 0, (int) $key + 1, 'UTF8');

        $nodeCurrent = framework_memcache_get($cmkeycurrent);

        if (!$nodeCurrent) {
            $nodeCurrent = [
                'wrd'     => false,
                'letters' => [],
                'link'    => [],
            ];
        }

        if ($l == $key + 1) {
            $nodeCurrent['wrd'] = $word;
        }

        framework_memcache_set($cmkeycurrent, $nodeCurrent, $time);

//        echo $cmckeyparent. PHP_EOL;

        $nodeParent = framework_memcache_get($cmckeyparent);
        if (!$nodeParent) {
            $nodeParent = [
                'wrd'     => false,
                'letters' => [],
                'link'    => [],
            ];
        }

        if (!in_array($letter, $nodeParent['letters'])) {
            $nodeParent['letters'][] = $letter;
            $nodeParent['link'][] = $cmkeycurrent;
        }

        framework_memcache_set($cmckeyparent, $nodeParent, $time);

//        var_dump($nodeParent);
        unset($nodeParent);
        unset($nodeCurrent);
    }
}

function game_word_wordAddRevertPostFix($word)
{
    $mckey = TREE_REVERT_POSTFIX_KEY;
    $mckeyPrefixRoot = 'root';
    $word = trim($word);
    $l = mb_strlen($word);
    $termSymbol = '$';

    $firstLetter = mb_substr($word, 0, 1, 'UTF8');

    $time = 0;

    for ($key = 0; $key < $l; $key++) {

        $letter = mb_substr($word, (int) $key, 1, 'UTF8');

        $prefix = mb_substr($word, 0, (int) $key + 1, 'UTF8');

        $prefixReverted = framework_helper_mbStrRev($prefix);

        $prefixRevertedLength = mb_strlen($prefixReverted);
//        echo $prefixRevertedLength;
        for ($k = 0; $k < $prefixRevertedLength; $k++) {
            $letter2 = mb_substr($prefixReverted, (int) $k, 1, 'UTF8');
//            echo ">" . $letter2;

            $cmkeycurrent = $mckey . mb_substr($prefixReverted, 0, (int) $k + 1, 'UTF8');
//            echo "&" . $cmkeycurrent . '&';

            //////
            $nodeCurrent = framework_memcache_get($cmkeycurrent);

            if (!$nodeCurrent) {
                $nodeCurrent = [
                    'cur'     => framework_helper_mbStrRev(mb_substr($prefixReverted, 0, (int) $k + 1, 'UTF8')),
                    'wrd'     => false,
                    'letters' => [],
                    'link'    => [],
                ];
            }
            if ($prefixRevertedLength == $k + 1) {
                //
                $cmkeycurrentEnd = $cmkeycurrent . "$";
                $nodeCurrentEnd = framework_memcache_get($cmkeycurrentEnd);

                if (!$nodeCurrentEnd) {
                    $nodeCurrentEnd = [
                        'wrd'     => false,
                        'letters' => [],
                        'link'    => [],
                    ];
                }
                framework_memcache_set($cmkeycurrentEnd, $nodeCurrentEnd, $time);
                //
                if (!in_array('$', $nodeCurrent['letters'])) {
                    $nodeCurrent['letters'][] = '$';
                    $nodeCurrent['link'][] = $cmkeycurrentEnd;
                }
            }

            if (($key + 1 == $l) && ($k + 1 == $l)) {
//                echo $word . PHP_EOL;
                $nodeCurrent['wrd'] = $word;
            }

            framework_memcache_set($cmkeycurrent, $nodeCurrent, $time);
            ///////

            if ($k == 0) {
                $cmckeyparent = $mckey . 'root';
            } else {
                $cmckeyparent = $mckey . mb_substr($prefixReverted, 0, (int) $k, 'UTF8');
            }

            //////
            $nodeParent = framework_memcache_get($cmckeyparent);

            if (!$nodeParent) {
                $nodeParent = [
                    'wrd'     => false,
                    'letters' => [],
                    'link'    => [],
                ];
            }

            if (!in_array($letter2, $nodeParent['letters'])) {
                $nodeParent['letters'][] = $letter2;
                $nodeParent['link'][] = $cmkeycurrent;
            }

            framework_memcache_set($cmckeyparent, $nodeParent, $time);
            ///////

            unset($nodeParent);
            unset($nodeCurrent);

            if (isset($nodeCurrentEnd)) {
                unset($nodeCurrentEnd);
            }
        }
    }
}

function game_word_checkWordUser($word)
{
    $word = game_word_convertWord($word);

    $mckey = TREE_KEY;

    $k = $mckey . $word;

    $n = framework_memcache_get($k);

    if (!$n) {
        return false;
    }

    if ($n['wrd'] === false) {
        return false;
    }

    return true;
}

/**
 * @deprecated
 * @param $x
 * @param $y
 * @param $word
 * @param $words
 */
function game_word_checkRevertPostFix($x, $y, $word, &$words)
{
    $table = game_game_getUserTable();
    $mckey = TREE_REVERT_POSTFIX_KEY;
    $mckeyPrefixRoot = 'root';
    $word = trim($word);
    $l = mb_strlen($word);
    $termSymbol = '$';

    $words = [];
    foreach (game_word_getAlphabet() as $letter) {
        $table[$x][$y]['var'] = $letter;
        $table[$x][$y]['used'] = 0;
        $mckey = TREE_REVERT_POSTFIX_KEY;
        $n = framework_memcache_get($mckey . $letter);

        game_word_checkRevertPostFixFoo($table, $x, $y, $n, $words);
    }
}

function game_word_checkRevertPostFixFoo($table, $x, $y, $n, &$words, $startx, $starty, $letter)
{
    $mckey = TREE_REVERT_POSTFIX_KEY;

    if ($n['wrd'] != false) {
        if (mb_strlen($n['wrd']) >= 0) {
//            if (!in_array($n['wrd'], $words)) {
            if (!isset($words[$n['wrd']])) {
//                $words[$n['wrd']] = [
//                    'wrd'    => $n['wrd'],
//                    'x'      => $startx,
//                    'y'      => $starty,
//                    'letter' => $letter,
//                ];
                addToList([
                    'wrd'    => $n['wrd'],
                    'x'      => $startx,
                    'y'      => $starty,
                    'letter' => $letter,
                ]);
//                echo '!!';
            }
        }
    }

    if (in_array('$', $n['letters'])) {
//        echo '$' . $n['cur'] . " " . $n['wrd'] . "<br>" .PHP_EOL;
        // search
        $nn = framework_memcache_get(TREE_KEY . $n['cur']);
//        var_dump("@" . $startx . ' x '. $starty . '@');
//        echo "<pre>";
//        var_dump($nn);
//        echo "</pre>";
        game_word_checkTree($table, $startx, $starty, $nn, $words, $startx, $starty, $letter);
//        echo "<pre>";
//        var_dump($nn);
//        echo "</pre>";
    }

    if (isset($table[$x - 1]) && ($table[$x - 1][$y]['letter'] != "")) {
        if (in_array($table[$x - 1][$y]['letter'], $n['letters'])) {
            if (!isset($table[$x - 1][$y]['used'])) {
                $table[$x - 1][$y]['used'] = 1;
            }
            if ($table[$x - 1][$y]['used'] > 0) {
                $i = array_search($table[$x - 1][$y]['letter'], $n['letters']);
                $nn = framework_memcache_get($n['link'][$i]);
                $table[$x - 1][$y]['used']--;
                game_word_checkRevertPostFixFoo($table, $x - 1, $y, $nn, $words, $startx, $starty, $letter);
                $table[$x - 1][$y]['used']++;
            }
        }
    }

    if (isset($table[$x + 1]) && ($table[$x + 1][$y]['letter'] != "")) {

        if (in_array($table[$x + 1][$y]['letter'], $n['letters'])) {
            if (!isset($table[$x + 1][$y]['used'])) {
                $table[$x + 1][$y]['used'] = 1;
            }
            if ($table[$x + 1][$y]['used'] > 0) {
                $i = array_search($table[$x + 1][$y]['letter'], $n['letters']);
                $nn = framework_memcache_get($n['link'][$i]);
                $table[$x + 1][$y]['used']--;
                game_word_checkRevertPostFixFoo($table, $x + 1, $y, $nn, $words, $startx, $starty, $letter);
                $table[$x + 1][$y]['used']++;
            }
        }
    }

    if (isset($table[$x][$y - 1]) && ($table[$x][$y - 1]['letter'] != "")) {
        if (in_array($table[$x][$y - 1]['letter'], $n['letters'])) {
            if (!isset($table[$x][$y - 1]['used'])) {
                $table[$x][$y - 1]['used'] = 1;
            }
            if ($table[$x][$y - 1]['used'] > 0) {
                $i = array_search($table[$x][$y - 1]['letter'], $n['letters']);
                $nn = framework_memcache_get($n['link'][$i]);
                $table[$x][$y - 1]['used']--;
                game_word_checkRevertPostFixFoo($table, $x, $y - 1, $nn, $words, $startx, $starty, $letter);
                $table[$x][$y - 1]['used']++;
            }
        }
    }

    if (isset($table[$x][$y + 1]) && ($table[$x][$y + 1]['letter'] != "")) {
        if (in_array($table[$x][$y + 1]['letter'], $n['letters'])) {
            if (!isset($table[$x][$y + 1]['used'])) {
                $table[$x][$y + 1]['used'] = 1;
            }
            if ($table[$x][$y + 1]['used'] > 0) {
                $i = array_search($table[$x][$y + 1]['letter'], $n['letters']);
                $nn = framework_memcache_get($n['link'][$i]);
                $table[$x][$y + 1]['used']--;
                game_word_checkRevertPostFixFoo($table, $x, $y + 1, $nn, $words, $startx, $starty, $letter);
                $table[$x][$y + 1]['used']++;
            }
        }
    }
}

function game_word_checkTree($table, $x, $y, $n, &$words, $startx, $starty, $letter)
{
    $mckey = TREE_REVERT_POSTFIX_KEY;


    if ($n['wrd'] != false) {
        if (mb_strlen($n['wrd']) >= 0) {
            if (!isset($words[$n['wrd']])) {
//                if ($n['wrd']=="баклажан"){
//                    echo "------------!<pre>";
//var_dump($n);
//var_dump($x);
//var_dump($y);
//var_dump($table);
//                    echo "</pre>";
//                    die();
//                }
                addToList([
                    'wrd'    => $n['wrd'],
                    'x'      => $startx,
                    'y'      => $starty,
                    'letter' => $letter,
                ]);
            }
        }
    }
    if (isset($table[$x - 1]) && ($table[$x - 1][$y]['letter'] != "")) {
        if (in_array($table[$x - 1][$y]['letter'], $n['letters'])) {
            if (!isset($table[$x - 1][$y]['used'])) {
                $table[$x - 1][$y]['used'] = 1;
            }
            if ($table[$x - 1][$y]['used'] > 0) {
                $i = array_search($table[$x - 1][$y]['letter'], $n['letters']);
                $nn = framework_memcache_get($n['link'][$i]);
                $table[$x - 1][$y]['used']--;
                game_word_checkTree($table, $x - 1, $y, $nn, $words, $startx, $starty, $letter);
                $table[$x - 1][$y]['used']++;
            }
        }
    }
    if (isset($table[$x + 1]) && ($table[$x + 1][$y]['letter'] != "")) {

        if (in_array($table[$x + 1][$y]['letter'], $n['letters'])) {
            if (!isset($table[$x + 1][$y]['used'])) {
                $table[$x + 1][$y]['used'] = 1;
            }
            if ($table[$x + 1][$y]['used'] > 0) {
                $i = array_search($table[$x + 1][$y]['letter'], $n['letters']);
                $nn = framework_memcache_get($n['link'][$i]);
//                var_dump("$$$" . $table[$x + 1][$y]['letter']."$".$x.'$'.$y. '$$$');
//                var_dump("$!");
//                if ($table[$x + 1][$y]['letter'] == "л") {var_dump($n);}
//                var_dump("!$");
                $table[$x + 1][$y]['used']--;
                game_word_checkTree($table, $x + 1, $y, $nn, $words, $startx, $starty, $letter);
                $table[$x + 1][$y]['used']++;
            }
        }
    }
    if (isset($table[$x][$y - 1]) && ($table[$x][$y - 1]['letter'] != "")) {
        if (in_array($table[$x][$y - 1]['letter'], $n['letters'])) {
            if (!isset($table[$x][$y - 1]['used'])) {
                $table[$x][$y - 1]['used'] = 1;
            }
            if ($table[$x][$y - 1]['used'] > 0) {
                $i = array_search($table[$x][$y - 1]['letter'], $n['letters']);
                $nn = framework_memcache_get($n['link'][$i]);
                $table[$x][$y - 1]['used']--;
                game_word_checkTree($table, $x, $y - 1, $nn, $words, $startx, $starty, $letter);
                $table[$x][$y - 1]['used']++;
            }
        }
    }
//    echo "<pre>";
//    var_dump("--------");
//    var_dump($n);
//    var_dump($x);
//    var_dump($y);
//    var_dump($table[$x - 1][$y]['letter']);
//    var_dump($table[$x + 1][$y]['letter']);
//    var_dump($table[$x][$y-1]['letter']);
//    var_dump($table[$x][$y+1]['letter']);
//    echo "</pre>";
    if (isset($table[$x][$y + 1]) && ($table[$x][$y + 1]['letter'] != "")) {
        if (in_array($table[$x][$y + 1]['letter'], $n['letters'])) {
//            var_dump("xy+1");
            if (!isset($table[$x][$y + 1]['used'])) {
                $table[$x][$y + 1]['used'] = 1;
            }
            if ($table[$x][$y + 1]['used'] > 0) {
                $i = array_search($table[$x][$y + 1]['letter'], $n['letters']);
                $nn = framework_memcache_get($n['link'][$i]);
                $table[$x][$y + 1]['used']--;
//                var_dump($table[$x][$y + 1]['letter']);
//                var_dump($i);
//                var_dump($y);
                game_word_checkTree($table, $x, $y + 1, $nn, $words, $startx, $starty, $letter);
                $table[$x][$y + 1]['used']++;
            }
        }
    }
}

function  game_word_convertWord($word)
{
    $word = trim($word);
    $word = mb_strtolower($word);
    $word = str_replace("ё", "е", $word);
    return $word;
}


function addToList($word)
{

    $words = game_game_getFindWords();
    if (!in_array($word['wrd'], $words)){
        $words[$word['wrd']] = $word;
    }
    game_game_setFindWords($words);
}