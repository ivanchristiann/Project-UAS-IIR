<?php
require_once __DIR__ . '/vendor/autoload.php';
include_once('simple_html_dom.php');
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\Tokenization\WhitespaceTokenizer;
use Phpml\FeatureExtraction\TfIdfTransformer;
use Phpml\Math\Distance\Euclidean;


$stemmerFactory = new \Sastrawi\Stemmer\StemmerFactory(); 
$stemmer = $stemmerFactory->createStemmer();

$stopwordFactory = new \Sastrawi\StopWordRemover\StopWordRemoverFactory(); 
$stopword = $stopwordFactory->createStopWordRemover();

$con = mysqli_connect("localhost", "root","", "project_uas_iir");

echo '<div style="float:left;"><a href="index.php">Home</a> | <a href="crawlerGoogleScholar.php">Crawling</a><div>';
echo '<b><font size="10">Welcome to Scientific Journals Search Engine</b></font><br><br>';
echo '<form method="POST" action=""> Keyword : <input type="text" name="keyword" value="' . (isset($_POST['keyword']) ? htmlspecialchars($_POST['keyword']) : '') . '"> <input type="submit" name="crawls" value="Crawl">';

echo '<div><input type="radio" name="method" value="Euclidean" checked>Euclidean';
echo '<input type="radio" name="method" value="dice">Dice';
echo '</div></form><br>';

if(isset($_POST["crawls"])){
	// $html = file_get_html("https://scholar.google.com/scholar?hl=en&as_sdt=0%2C5&q=" . str_replace(" ", "+", $_POST['keyword']));
	// // echo $html;
	// foreach ($html->find('div[class="gs_r gs_or gs_scl"]') as $journals) {
	// 	$title = strip_tags($journals->find('h3[class="gs_rt"] a',0)->innertext);
	// 	$authors = $journals->find('div[class="gs_a"]',0)->innertext;
	// 	$link = $journals->find('a',0)->href;
	// 	$numberCitation = explode(' ' ,($journals->find('div[class="gs_fl gs_flb"] a',2)->innertext));

	// 	$linkAuthor = $journals->find('div[class="gs_ri"] div[class="gs_a"] a',0);

	// 	if ($linkAuthor) {
	// 		$linkAuthor = "https://scholar.google.com" . $linkAuthor->href;
	// 		$htmlAuthor = file_get_html($linkAuthor);

	// 		$journalAuthor = $htmlAuthor->find('tbody[id="gsc_a_b"]',0);

	// 		foreach ($journalAuthor->find('tr[class="gsc_a_tr"]') as $journalsTitle) {
	// 			$titleJournal = $journalsTitle->find('a[class="gsc_a_at"]',0)->innertext;

	// 			if (strtolower($title) === strtolower($titleJournal)) {
	// 				$linkJournal = "https://scholar.google.com" . $journalsTitle->find('a[class="gsc_a_at"]', 0)->href;
	// 				$replace1 = str_replace('oe=ASCII', '', $linkJournal);
	// 				$replace2 = str_replace('amp;', '', $replace1);

	// 				$journal = file_get_html($replace2);
	// 				$abstrak = $journal->find('div[class="gsh_csp"]',0)->innertext;
	// 				break;
	// 			}	
	// 		}
	// 	}

	$sql = "SELECT * FROM training WHERE title LIKE ? OR abstract LIKE ?";
	$stmt = $con->prepare($sql);
	$keyword = "%".$_POST['keyword']."%";
	$stmt->bind_param("ss", $keyword, $keyword);
	$stmt->execute();
	$res = $stmt->get_result();

	$sample_data = [];
	$training_data = [];
	$i = 1;

	if ($res->num_rows > 0) {
		$stemmer = $stemmer->stem($_POST['keyword']);
		$stopword = $stopword->remove($stemmer);

		$sample_data[0] = $stopword;

		while($row = $res->fetch_assoc()) {
			$sample_data[$i] = $row['title'];
			$training = array("title" => $row['title'], "number_citations" => $row['number_citations'], "authors" => $row['authors'], "abstract" => $row['abstract']);
			$training_data[] = $training;
			$i++;
		}
	} 

	$tf = new TokenCountVectorizer(new WhitespaceTokenizer()); 
	$tf->fit($sample_data); 
	$tf->transform($sample_data); 
	$vocabulary = $tf->getVocabulary();

	$tfidf = new TfIdfTransformer ($sample_data);
	$tfidf->transform($sample_data);

	$total = count($sample_data);
	if (isset($_POST["method"])) {
		if ($_POST["method"] == 'Euclidean') {
			$euclidean = new Euclidean();
			for($i=0;$i<$total-1;$i++) {
				$training_data[$i]['similarity'] = $euclidean->distance($sample_data[$total-1],$sample_data[$i]); 
			}
		}else if($_POST["method"] == 'dice'){
			$query_idx = $total-1;
			for($i=0;$i<$query_idx;$i++)
			{
				$numerator = 0.0;
				$denom_wkq = 0.0;
				$denom_wkj = 0.0;
				for($x=0;$x<count($sample_data[$i]); $x++)
				{
					$numerator += $sample_data[$query_idx] [$x] * $sample_data[$i] [$x]; 
					$denom_wkq += pow($sample_data[$query_idx] [$x],2);
					$denom_wkj += pow($sample_data[$i][$x],2);
				}

				if((0.5*$denom_wkq + 0.5*$denom_wkj) != 0)
				{
					$training_data[$i]['similarity'] = $numerator / (0.5*$denom_wkq + 0.5*$denom_wkj);
				}
				else $training_data[$i]['similarity'] = 0;
			}
		}
	}

	foreach ($training_data as $data) {
		echo "Title : " . strip_tags($data['title']). "<br>";
		echo "Authors : " . str_replace('&nbsp;', ' ', $data['authors']). "<br>";
		echo "Abstract : " . $data['abstract'] . "<br>";
		echo "Number of Citation : " . $data['number_citations'] . "<br>";
		echo "Similarity Scrore: " . $data['similarity'] . "<br>";
		echo "<hr>";
	}
}
?>