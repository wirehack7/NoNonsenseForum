<?php //search this forum's own threads (see 'searchThreads ()' in 'lib/functions.php')
/* ====================================================================================================================== */
/* NoNonsense Forum v26 © Copyright (CC-BY) Kroc Camen 2010-2015
   licenced under Creative Commons Attribution 3.0 <creativecommons.org/licenses/by/3.0/deed.en_GB>
   you may do whatever you want to this code as long as you give credit to Kroc Camen, <camendesign.com>
*/

//bootstrap the forum; you should read that file first
require_once './start.php';

//the search query, if any (this is deliberately not URL-decoded specially -- `$_GET` already is)
define ('QUERY', mb_substr (trim ((string) @$_GET['q']), 0, SIZE_SEARCH));

//run the search (only if the feature is switched on, and a query was actually given)
$RESULTS = FORUM_SEARCH && QUERY ? searchThreads (QUERY) : array ();

//paginate the results, the same way index.php / thread.php paginate threads / replies
$PAGES = $RESULTS ? (int) ceil (count ($RESULTS) / FORUM_SEARCH_RESULTS) : 1;
$PAGE  = !PAGE || PAGE > $PAGES ? 1 : PAGE;
$page_results = array_slice ($RESULTS, ($PAGE-1) * FORUM_SEARCH_RESULTS, FORUM_SEARCH_RESULTS);

$template = prepareTemplate (
        THEME_ROOT.'search.html', '',
        sprintf (THEME_TITLE_SEARCH, QUERY)
)->remove (array (
        //search switched off entirely in 'config.php'?
        '#nnf_search-disabled'  => FORUM_SEARCH,
        //no query given yet (i.e. just arrived at the page)?
        '#nnf_search-noquery'   => !FORUM_SEARCH || QUERY,
        //a query was given, but nothing matched?
        '#nnf_search-noresults' => !FORUM_SEARCH || !QUERY || $page_results,
        //got at least one result to show?
        '#nnf_search-results'   => !FORUM_SEARCH || !QUERY || !$page_results,
        '#nnf_search-pages'     => !FORUM_SEARCH || !QUERY || !$page_results,
        '#nnf_search-prev'      => $PAGE <= 1,
        '#nnf_search-next'      => $PAGE >= $PAGES
));

if ($page_results) {
        $template->set (array (
                'a#nnf_search-prev@href' => FORUM_PATH.'search.php?'.http_build_query (array ('q'=>QUERY, 'page'=>$PAGE-1)),
                'a#nnf_search-next@href' => FORUM_PATH.'search.php?'.http_build_query (array ('q'=>QUERY, 'page'=>$PAGE+1))
        ));

        //get the dummy list-item to repeat (removes it and takes a copy)
        $item = $template->repeat ('.nnf_result');

        foreach ($page_results as $result) $item->set (array (
                'a.nnf_result-name'             => $result['title'],
                'a.nnf_result-name@href'        => $result['link'],
                '.nnf_result-author'            => $result['author'],
                'time.nnf_result-time'          => date (DATE_FORMAT, $result['time']),
                'time.nnf_result-time@datetime' => date ('c', $result['time']),
                '.nnf_result-snippet'           => $result['snippet']
        ))->remove (array (
                //is this result's author a mod?
                '.nnf_result-author@class'      => isMod ($result['author']) ? false : 'nnf_mod'
        ))->next ();
}

//call the theme-specific templating function, in 'theme.php', before outputting
theme_custom ($template);
exit ($template);

?>
