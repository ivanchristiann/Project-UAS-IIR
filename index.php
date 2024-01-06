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

	usort($training_data, function($a, $b) {
		return $a['title'] <=> $b['title'];
	});

	foreach ($training_data as $data) {
		echo "Title : " . strip_tags($data['title']). "<br>";
		echo "Authors : " . str_replace('&nbsp;', ' ', $data['authors']). "<br>";
		echo "Abstract : " . $data['abstract'] . "<br>";
		echo "Number of Citation : " . $data['number_citations'] . "<br>";
		echo "Similarity Scrore: " . $data['similarity'] . "<br>";
		echo "<hr>";
	}



	$expansion = array();
	$tfidf_total = array();
	for ($i = 0; $i < 3; $i++) {
		$expansion[] = $training_data[$i]['title']; 
	}

	$tf = new TokenCountVectorizer(new WhitespaceTokenizer());
	$tf->fit($expansion);
	$tf->transform($expansion);

	$tfidf = new TfIdfTransformer($expansion);
	$tfidf->transform($expansion);

	$vocabulary = $tf->getVocabulary();

	for ($i = 0; $i < count($vocabulary); $i++) {
		$tfidf_total[$i] = 0;
	}

	for ($i = 0; $i < count($expansion); $i++) {
		for ($j = 0; $j < count($vocabulary) - 1; $j++) {
			$tfidf_total[$j] += $expansion[$i][$j];
		}
	}

	usort($tfidf_total, function($a, $b) {
		return $a <=> $b;
	});

	foreach ($tfidf_total as $key => $value) {
		// if (!in_array(strtolower($vocabulary[$key]),$_POST['keyword'])) {
		$expansion[] = $vocabulary[$key];
		// }
	}

	echo "Related Search";
	echo "<ul>";
	for ($i=3; $i < count($expansion); $i++) { 
		echo "<li>". $expansion[$i] . "</li>";
	}
	echo "</ul>";
}
?>