<?php
require_once __DIR__ . '/vendor/autoload.php';
include_once('simple_html_dom.php');

$i=0;
$stemmerFactory = new \Sastrawi\Stemmer\StemmerFactory(); 
$stemmer = $stemmerFactory->createStemmer();

$stopwordFactory = new \Sastrawi\StopWordRemover\StopWordRemoverFactory(); 
$stopword = $stopwordFactory->createStopWordRemover();

echo '<b><font size="10">Data Crawling From Google Scholar</b></font><br><br>';
echo '<form method="POST" action="">
Keyword : <input type="text" name="keyword"> <input type="submit" name="crawls" value="Crawl">';
echo '<div><input type="radio" name="method" value="jaccard" checked>Jaccard';
echo '<input type="radio" name="method" value="manhattan">Manhattan';
echo '</div></form><br>';

if(isset($_POST["crawls"])){
	$html = file_get_html("https://scholar.google.com/scholar?hl=en&as_sdt=0%2C5&q=" . str_replace(" ", "+", $_POST['keyword']));

	foreach ($html->find('div[class="gs_r gs_or gs_scl"]') as $journals) {
		$title = $journals->find('h3[class="gs_rt"] a',0)->innertext;
		$authors = $journals->find('div[class="gs_a"]',0)->innertext;
		$link = $journals->find('a',0)->href;
		$numberCitation = explode(' ' ,($journals->find('div[class="gs_fl gs_flb"] a',2)->innertext));

		// $htmlArticle = file_get_html($link);
		// $abstrak = $htmlArticle->find('div[class="c-article-section__content"] p',0)->innertext;
		
		echo "Title : " . strip_tags($title). "<br>";
		echo "Authors : " . str_replace('&nbsp;', ' ', $authors). "<br>";
		echo "Number of Citation : " . strip_tags($numberCitation[2]). "<br>";
		echo "Link : " . $link . "<br>";
		// echo "Abstrak : " . $abstrak . "<br><br>";
		echo "Similarity Score : " . $link . "<br>";

		echo "<hr>";
	}
}
// if(isset($_POST["crawls"])){
// 	$html = file_get_html("https://link.springer.com/chapter/10.1007/978-3-642-57489-4_59");
// 	$abstrak = $html->find('div[class="c-article-section__content"] p',0)->innertext;
// 	echo "Abstrak : " . $abstrak . "<br><br>";
// }
?>