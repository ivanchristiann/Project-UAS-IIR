<?php
require_once __DIR__ . '/vendor/autoload.php';
include_once('simple_html_dom.php');

$i=0;
$stemmerFactory = new \Sastrawi\Stemmer\StemmerFactory(); 
$stemmer = $stemmerFactory->createStemmer();

$stopwordFactory = new \Sastrawi\StopWordRemover\StopWordRemoverFactory(); 
$stopword = $stopwordFactory->createStopWordRemover();

$con = mysqli_connect("localhost", "root","", "project_uas_iir");

echo '<div style="float:left;"><a href="index.php">Home</a> | <a href="crawlerGoogleScholar.php">Crawling</a><div>';

echo '<b><font size="10">Data Crawling From Google Scholar</b></font><br><br>';
echo '<form method="POST" action=""> Keyword : <input type="text" name="keyword" value="' . (isset($_POST['keyword']) ? htmlspecialchars($_POST['keyword']) : '') . '"> <input type="submit" name="crawls" value="Crawl">';
echo "<br>";
echo "<br>";

if(isset($_POST["crawls"])){
	$html = file_get_html("https://scholar.google.com/scholar?hl=en&as_sdt=0%2C5&q=" . str_replace(" ", "+", $_POST['keyword']));
	foreach ($html->find('div[class="gs_r gs_or gs_scl"]') as $journals) {
		$title = strip_tags($journals->find('h3[class="gs_rt"] a',0)->innertext);
		$authors = strip_tags($journals->find('div[class="gs_a"]',0)->innertext);
		$link = $journals->find('a',0)->href;
		$numberCitation = explode(' ' ,($journals->find('div[class="gs_fl gs_flb"] a',2)->innertext));

		$stemTitle = $stemmer->stem($title);
		$stopTitle = $stopword->remove($stemTitle);

		$linkAuthor = $journals->find('div[class="gs_ri"] div[class="gs_a"] a',0);

		if ($linkAuthor) {
			$linkAuthor = "https://scholar.google.com" . $linkAuthor->href;
			$htmlAuthor = file_get_html($linkAuthor);

			$journalAuthor = $htmlAuthor->find('tbody[id="gsc_a_b"]',0);

			foreach ($journalAuthor->find('tr[class="gsc_a_tr"]') as $journalsTitle) {
				$titleJournal = $journalsTitle->find('a[class="gsc_a_at"]',0)->innertext;

				if (strtolower($title) === strtolower($titleJournal)) {
					$linkJournal = "https://scholar.google.com" . $journalsTitle->find('a[class="gsc_a_at"]', 0)->href;
					$replace1 = str_replace('oe=ASCII', '', $linkJournal);
					$replace2 = str_replace('amp;', '', $replace1);

					$journal = file_get_html($replace2);
					$abstrak = $journal->find('div[class="gsh_csp"]',0)->innertext;
					break;
				}	
			}
		}
		echo "Title : " . strip_tags($title). "<br>";
		echo "Authors : " . str_replace('&nbsp;', ' ', $authors). "<br>";
		echo "Number of Citation : " . strip_tags($numberCitation[2]). "<br>";

		if ($linkAuthor) {
			echo "Abstrak : " . $abstrak;			
		}

		if ($linkAuthor == false) {
			$abstrak = '';			
		}
		$sql = "INSERT INTO `training`(`title`, `number_citations`, `authors`, `abstract`) VALUE('".$stopTitle."','". $numberCitation[2] . "','" . strip_tags($authors) . "','" . $abstrak . "')";

			mysqli_query($con, $sql);
			echo "<hr>";


		}
	}
?>