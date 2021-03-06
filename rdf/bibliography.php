<?php

error_reporting(E_ALL ^ E_DEPRECATED);

// Publications to triples

require_once (dirname(dirname(__FILE__)) . '/adodb5/adodb.inc.php');

require_once('php-json-ld/jsonld.php');

require_once (dirname(dirname(__FILE__)) . '/parse_authors.php');


//--------------------------------------------------------------------------------------------------
$db = NewADOConnection('mysql');
$db->Connect("localhost", 
	'root' , '' , 'afd');

// Ensure fields are (only) indexed by column name
$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;


$db->EXECUTE("set names 'utf8'"); 


//--------------------------------------------------------------------------------------------------
function get_pdf_details($pdf)
{
	$obj = null;
	
	$url = 'http://bionames.org/bionames-archive/pdfstore?url=' . urlencode($pdf) . '&noredirect&format=json';

	$opts = array(
	  CURLOPT_URL =>$url,
	  CURLOPT_FOLLOWLOCATION => TRUE,
	  CURLOPT_RETURNTRANSFER => TRUE
	);
	
	$ch = curl_init();
	curl_setopt_array($ch, $opts);
	$data = curl_exec($ch);
	$info = curl_getinfo($ch); 
	curl_close($ch);
	
	if ($data != '')
	{
		$obj = json_decode($data);
	}
	
	return $obj;
}

//--------------------------------------------------------------------------------------------------
function get_pdf_images($sha1)
{
	$obj = null;
	
	$url = 'http://bionames.org/bionames-archive/documentcloud/' . $sha1 . '.json';

	$opts = array(
	  CURLOPT_URL =>$url,
	  CURLOPT_FOLLOWLOCATION => TRUE,
	  CURLOPT_RETURNTRANSFER => TRUE
	);
	
	$ch = curl_init();
	curl_setopt_array($ch, $opts);
	$data = curl_exec($ch);
	$info = curl_getinfo($ch); 
	curl_close($ch);
	
	if ($data != '')
	{
		$obj = json_decode($data);
	}
	
	return $obj;
}


$sql = 'SELECT DISTINCT *
FROM bibliography
WHERE PARENT_PUBLICATION_GUID ="8823e1b7-c40a-4a59-a8f1-4c2cf0ad430a";';


$sql = 'SELECT DISTINCT *
FROM bibliography
WHERE PUBLICATION_GUID ="49cedf3d-3d83-478d-93d1-30c74527f168";';

$sql = 'SELECT DISTINCT *
FROM bibliography
WHERE PUBLICATION_GUID ="25d1f178-cadb-465a-9937-d75bbd17ca5c";';


$sql = "SELECT DISTINCT afd.TAXON_GUID, NAME_GUID, SCIENTIFIC_NAME, bibliography.* 
FROM bibliography 
INNER JOIN afd USING(PUBLICATION_GUID) 
WHERE afd.Genus IN ('Ctenophorus')";

// Molluscan Research
$sql = 'SELECT DISTINCT *
FROM bibliography
WHERE PARENT_PUBLICATION_GUID ="fc5b328b-8b89-46a8-9465-a0635763c311";';


/*
// book
$sql = 'SELECT DISTINCT *
FROM bibliography
WHERE PUBLICATION_GUID ="d9e0e56c-30f4-4391-b05d-038463908d8f";';

// chapter
$sql = 'SELECT DISTINCT *
FROM bibliography
WHERE PUBLICATION_GUID ="b60c708e-85bf-4543-9955-947bb5143e6a";';
*/


$sql = "SELECT DISTINCT afd.TAXON_GUID, NAME_GUID, SCIENTIFIC_NAME, bibliography.* 
FROM bibliography 
INNER JOIN afd USING(PUBLICATION_GUID) 
WHERE afd.Genus IN ('Lomanella')";



$sql = 'SELECT DISTINCT *
FROM bibliography
WHERE PUB_PARENT_JOURNAL_TITLE = "Journal of the American Mosquito Control Association";';

$sql = 'SELECT  *
FROM bibliography
WHERE PUBLICATION_GUID ="0d76aea2-8896-4dae-91e9-4d6f9441dc97";';

$sql = "SELECT DISTINCT afd.TAXON_GUID, NAME_GUID, SCIENTIFIC_NAME, bibliography.* 
FROM bibliography 
INNER JOIN afd USING(PUBLICATION_GUID) 
WHERE afd.Genus IN ('Lomanella')";

$sql = 'SELECT DISTINCT afd.TAXON_GUID, NAME_GUID, SCIENTIFIC_NAME, bibliography.* 
FROM bibliography 
INNER JOIN afd USING(PUBLICATION_GUID) 
WHERE afd.Genus IN (
"Allobunus",
"Chilobunus",
"Chrestobunus",
"Dingupa",
"Dipristes",
"Eubunus",
"Glyptobunus",
"Miobunus",
"Phanerobunus",
"Phoxobunus",
"Rhynchobunus",
"Thelbunus",
"Triaenobunus",
"Allonuncia",
"Ankylonuncia",
"Bryonuncia",
"Callihamina",
"Callihamus",
"Calliuncus",
"Cluniella",
"Conoculus",
"Equitius",
"Heteronuncia",
"Hickmanoxyomma",
"Holonuncia",
"Leionuncia",
"Mestonia",
"Notonuncia",
"Nucina",
"Nunciella",
"Nuncioides",
"Odontonuncia",
"Paranuncia",
"Parattahia",
"Perthacantha",
"Pyenganella",
"Stylonuncia",
"Tasmanonuncia",
"Tasmanonyx",
"Triaenonyx",
"Yatala",
"Breviacantha",
"Lomanella"
);';


$enhance_authors 		= true;
$enhance_metadata 		= true;
$enhance_identifiers	= true;
$enhance_pdf			= true;


$page = 1000;
$offset = 0;

$done = false;

while (!$done)
{
	$sql = 'SELECT DISTINCT *
	FROM bibliography';
	
	// A specific journal or publication, otherwise we are getting everything
	//$sql .= ' WHERE PUBLICATION_GUID = "07e5fb9a-6ac2-4bba-9b16-aaa8000a0db1"';
	
	//$sql .= ' WHERE updated > "2018-06-16"';
	$sql .= ' WHERE updated > "2018-06-21"';
	
	$sql .= ' LIMIT ' . $page . ' OFFSET ' . $offset;

	$result = $db->Execute($sql);
	if ($result == false) die("failed [" . __FILE__ . ":" . __LINE__ . "]: " . $sql);


	while (!$result->EOF) 
	{
		// triples
	
		$triples = array();
	
		$subject_id = 'https://biodiversity.org.au/afd/publication/' . $result->fields['PUBLICATION_GUID'];
	
		$s = '<' . $subject_id . '>';
		
		// generic UUID
		$triples[] = $s . ' <http://schema.org/identifier> <urn:uuid:' . $result->fields['PUBLICATION_GUID'] . '> .';
		
	
		if ($result->fields['PUB_AUTHOR'] != '')
		{
			if ($enhance_authors)
			{
				$p = parse_authors($result->fields['PUB_AUTHOR']);
			
				// ordered list of authors
				$authorList_id = '<' . $subject_id . '/authorList'  . '>';
				$triples[] = $s . ' <http://purl.org/ontology/bibo/authorList> ' . $authorList_id . ' .';
				$triples[] = $authorList_id . ' <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://schema.org/ItemList> .';

			
				$n = count($p);
				for ($i = 0; $i < $n; $i++)
				{
					$author_id = '<' . $subject_id . '#creator/' . $p[$i]->id . '>';
				
					// assume our faked id is same for all occurences of author 
					$author_id = '<' . 'https://biodiversity.org.au/afd/publication/' . '#creator/' . $p[$i]->id . '>';				
				
					$triples[] = $s . ' <http://schema.org/creator> ' .  $author_id . ' .';
					$triples[] = $author_id . ' <http://schema.org/name> ' . '"' . addcslashes($p[$i]->name, '"') . '"' . ' .';
				
					if (isset($author->givenName))
					{
						$triples[] = $author_id . ' <http://schema.org/givenName> ' . '"' . addcslashes($p[$i]->givenName, '"') . '"' . ' .';				
					}
					if (isset($author->familyName))
					{
						$triples[] = $author_id . ' <http://schema.org/familyName> ' . '"' . addcslashes($p[$i]->familyName, '"') . '"' . ' .';				
					}

					$triples[] = $author_id . ' <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> ' . ' <http://schema.org/' . $p[$i]->type . '>' . ' .';			
				
				
					// add to ordered list of authors
					// list http://purl.org/ontology/bibo/authorList
					$index = $i + 1;
				
					$listItem_id = '<' . $subject_id . '/authorList' . '#' . $index . '>';
				
					$triples[] = $listItem_id . ' <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://schema.org/ListItem> .';			
					$triples[] = $listItem_id . ' <http://schema.org/position> "' . $index . '" .';			
					$triples[] = $listItem_id . ' <http://schema.org/item> ' . $author_id . ' .';			
			
					$triples[] = $authorList_id . ' <http://schema.org/itemListElement> ' . $listItem_id . ' .';
				
				}
			
				
		
			}
			else
			{
				$triples[] = $s . ' <http://schema.org/creator> ' . '"' . addcslashes($result->fields['PUB_AUTHOR'], '"') . '" .';
			}
		}
	
		if ($result->fields['PUB_YEAR'] != '')
		{
			$triples[] = $s . ' <http://schema.org/datePublished> ' . '"' . addcslashes($result->fields['PUB_YEAR'], '"') . '" .';
		}
	
		if ($result->fields['PUB_TITLE'] .= '')
		{
			$title = $result->fields['PUB_TITLE'];
			
			// clean
			$title = strip_tags($title);
			
			$title = preg_replace('/\n/', '', $title);
			$title = preg_replace('/\r/', '', $title);
		
		
			$triples[] = $s . ' <http://schema.org/name> ' . '"' . addcslashes($title, '"\\') . '" .';
		}
	
		if ($result->fields['PUB_PAGES'] != '')
		{
			$triples[] = $s . ' <http://schema.org/pagination> ' . '"' . addcslashes($result->fields['PUB_PAGES'], '"') . '" .';
		}
	
		/*
		if ($result->fields['PUB_PARENT_JOURNAL_TITLE'] != '')
		{
			$triples[] = $s . ' <http://prismstandard.org/namespaces/basic/2.1/publicationName> ' . '"' . addcslashes($result->fields['PUB_PARENT_JOURNAL_TITLE'], '"') . '" .';
		}
		*/
	
		if ($result->fields['PARENT_PUBLICATION_GUID'] != '')
		{
			$container_id = '<https://biodiversity.org.au/afd/publication/' . $result->fields['PARENT_PUBLICATION_GUID'] . '>';
			$triples[] = $s . ' <http://schema.org/isPartOf> ' . $container_id . ' .';
			
			// generic UUID
			$triples[] = $container_id . ' <http://schema.org/identifier> <urn:uuid:' . $result->fields['PUBLICATION_GUID'] . '> .';
			
			// Journal
			if ($result->fields['PUB_PARENT_JOURNAL_TITLE'] != '')
			{
				$triples[] = $container_id . ' <http://schema.org/name> ' .  '"' . addcslashes($result->fields['PUB_PARENT_JOURNAL_TITLE'], '"') . '" .';
				$triples[] = $container_id . ' <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://schema.org/Periodical> .';
			
				if ($enhance_metadata)
				{
					if ($result->fields['issn'] != '')
					{
						$triples[] = $container_id . ' <http://schema.org/issn> ' .  '"' . addcslashes($result->fields['issn'], '"') . '" .';
						$triples[] = $container_id . ' <http://schema.org/sameAs> <http://worldcat.org/issn/' . $result->fields['issn'] . '> .';
					}
			
				}
			}
		
			// Book
			if ($result->fields['PUB_PARENT_BOOK_TITLE'] != '')
			{
				$triples[] = $container_id . ' <http://schema.org/name> ' .  '"' . addcslashes($result->fields['PUB_PARENT_BOOK_TITLE'], '"') . '" .';
				$triples[] = $container_id . ' <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://schema.org/Book> .';
			}
		
		}	
	
		if ($result->fields['PUB_PUBLISHER'] != '')
		{
			$triples[] = $s . ' <http://schema.org/publisher> ' . '"' . addcslashes($result->fields['PUB_PUBLISHER'], '"') . '" .';
		}
	
		if ($enhance_metadata)
		{
			switch ($result->fields['PUB_TYPE'])
			{
				case 'Book':
					break;
				
				case 'Article in Journal':
				default:
					if ($result->fields['volume'] != '')
					{
						$triples[] = $s . ' <http://schema.org/volume> ' . '"' . addcslashes($result->fields['volume'], '"') . '" .';
					}
					if ($result->fields['issue'] != '')
					{
						$triples[] = $s . ' <http://schema.org/issueNumber> ' . '"' . addcslashes($result->fields['issue'], '"') . '" .';
					}
					if ($result->fields['spage'] != '')
					{
						$triples[] = $s . ' <http://schema.org/pageStart> ' . '"' . addcslashes($result->fields['spage'], '"') . '" .';
					}
					if ($result->fields['epage'] != '')
					{
						$triples[] = $s . ' <http://schema.org/pageEnd> ' . '"' . addcslashes($result->fields['epage'], '"') . '" .';
					}
					break;
			}		
		}
	
		if ($enhance_identifiers)
		{
			// BioStor
			if ($result->fields['biostor'] != '')
			{
				$identifier_id = '<' . $subject_id . '#biostor' . '>';
		
				$triples[] = $s . ' <http://schema.org/identifier> ' . $identifier_id . '.';			
				$triples[] = $identifier_id . ' <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://schema.org/PropertyValue> .';
				$triples[] = $identifier_id . ' <http://schema.org/propertyID> ' . '"biostor"' . '.';
				$triples[] = $identifier_id . ' <http://schema.org/value> ' . '"' . addcslashes($result->fields['biostor'], '"') . '"' . '.';
			
				//$triples[] = $s . ' <http://schema.org/sameAs> ' . '<https://hdl.handle.net/' . $result->fields['handle'] . '> ' . '. ';
			
			}	
		
			// DOI
			if ($result->fields['doi'] != '')
			{
				$identifier_id = '<' . $subject_id . '#doi' . '>';
		
				$triples[] = $s . ' <http://schema.org/identifier> ' . $identifier_id . '.';			
				$triples[] = $identifier_id . ' <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://schema.org/PropertyValue> .';
				$triples[] = $identifier_id . ' <http://schema.org/propertyID> ' . '"doi"' . '.';
				$triples[] = $identifier_id . ' <http://schema.org/value> ' . '"' . addcslashes($result->fields['doi'], '"') . '"' . '.';
			
				$triples[] = $s . ' <http://schema.org/sameAs> ' . '<https://doi.org/' . $result->fields['doi'] . '> ' . '. ';
			
			}
			
			// Handle
			if ($result->fields['handle'] != '')
			{
				$identifier_id = '<' . $subject_id . '#handle' . '>';
		
				$triples[] = $s . ' <http://schema.org/identifier> ' . $identifier_id . '.';			
				$triples[] = $identifier_id . ' <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://schema.org/PropertyValue> .';
				$triples[] = $identifier_id . ' <http://schema.org/propertyID> ' . '"handle"' . '.';
				$triples[] = $identifier_id . ' <http://schema.org/value> ' . '"' . addcslashes($result->fields['handle'], '"') . '"' . '.';
			
				$triples[] = $s . ' <http://schema.org/sameAs> ' . '<https://hdl.handle.net/' . $result->fields['handle'] . '> ' . '. ';
			
			}	
			
			// JSTOR
			if ($result->fields['jstor'] != '')
			{
				$identifier_id = '<' . $subject_id . '#jstor' . '>';
		
				$triples[] = $s . ' <http://schema.org/identifier> ' . $identifier_id . '.';			
				$triples[] = $identifier_id . ' <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://schema.org/PropertyValue> .';
				$triples[] = $identifier_id . ' <http://schema.org/propertyID> ' . '"jstor"' . '.';
				$triples[] = $identifier_id . ' <http://schema.org/value> ' . '"' . addcslashes($result->fields['jstor'], '"') . '"' . '.';
			
				$triples[] = $s . ' <http://schema.org/sameAs> ' . '<https://www.jstor.org/stable/' . $result->fields['jstor'] . '> ' . '. ';
			
			}		
				
			
		
			// ISBN
			if ($result->fields['isbn'] != '')
			{
				$triples[] = $s . ' <http://schema.org/isbn> ' . '"' . addcslashes($result->fields['doi'], '"') . '"' . '.';
			}	
		
			// PMID
			if ($result->fields['pmid'] != '')
			{
				$identifier_id = '<' . $subject_id . '#pmid' . '>';
		
				$triples[] = $s . ' <http://schema.org/identifier> ' . $identifier_id . '.';			
				$triples[] = $identifier_id . ' <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://schema.org/PropertyValue> .';
				$triples[] = $identifier_id . ' <http://schema.org/propertyID> ' . '"pmid"' . '.';
				$triples[] = $identifier_id . ' <http://schema.org/value> ' . '"' . addcslashes($result->fields['pmid'], '"') . '"' . '.';
			
				$triples[] = $s . ' <http://schema.org/sameAs> ' . '<https://www.ncbi.nlm.nih.gov/pubmed/' . $result->fields['pmid'] . '> ' . '. ';
			
			}		
		
			// SICI-style identifier to help automate citation linking	
			$sici = array();
				
			if ($result->fields['issn'] != '')
			{
				$sici[] = $result->fields['issn'];
			
				if ($result->fields['PUB_YEAR'] != '')
				{
					$sici[] = '(' . $result->fields['PUB_YEAR'] . ')';
				}										

				if ($result->fields['volume'] != '')
				{
					$sici[] = $result->fields['volume'];
				}

				if ($result->fields['spage'] != '')
				{
					$sici[] = '<' . $result->fields['spage'] . '>';
				}
		
				if (count($sici) == 4)
				{
					$identifier_id = '<' . $subject_id . '#sici' . '>';

					$triples[] = $s . ' <http://schema.org/identifier> ' . $identifier_id . '.';			
					$triples[] = $identifier_id . ' <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://schema.org/PropertyValue> .';
					$triples[] = $identifier_id . ' <http://schema.org/propertyID> ' . '"sici"' . '.';
					$triples[] = $identifier_id . ' <http://schema.org/value> ' . '"' . addcslashes(join('', $sici), '"') . '"' . '.';
		
				}
			}
		
			// URL
			if ($result->fields['url'] != '')
			{
				$triples[] = $s . ' <http://schema.org/url> ' . '"' . addcslashes($result->fields['url'], '"') . '"' . '.';
			}	
		
			// Zenodo
			if ($result->fields['zenodo'] != '')
			{
				$identifier_id = '<' . $subject_id . '#zenodo' . '>';

				$triples[] = $s . ' <http://schema.org/identifier> ' . $identifier_id . '.';			
				$triples[] = $identifier_id . ' <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://schema.org/PropertyValue> .';
				$triples[] = $identifier_id . ' <http://schema.org/propertyID> ' . '"zenodo"' . '.';
				$triples[] = $identifier_id . ' <http://schema.org/value> ' . '"' . $result->fields['zenodo'] . '"' . '.';
				
				// sameAs link?
				//$triples[] = $s . ' <http://schema.org/sameAs> <https://zenodo.org/record/' . $result->fields['zenodo'] . '> .';				
			}	
		
		}
	
		if ($enhance_pdf)
		{
			if ($result->fields['pdf'] != '')
			{
				$pdf_id = '<' . $subject_id . '#pdf' . '>';
		
				$triples[] = $s . ' <http://schema.org/encoding> ' . $pdf_id . ' .';

				// PDF
				$triples[] = $pdf_id . ' <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://schema.org/MediaObject> .';
				$triples[] = $pdf_id . ' <http://schema.org/contentUrl> ' . '"' . addcslashes($result->fields['pdf'], '"') . '"' . '.';
				$triples[] = $pdf_id . ' <http://schema.org/fileFormat> "application/pdf" .';
			
				// SHA1 and page images
				$sha1 = '';
				$obj = get_pdf_details($result->fields['pdf']);
			
				if ($obj)
				{
					$sha1 = $obj->sha1;
					$triples[] = $pdf_id . ' <http://id.loc.gov/vocabulary/preservation/cryptographicHashFunctions/sha1> ' . '"' . addcslashes($obj->sha1, '"') . '"' . ' .';			
				
					$images = get_pdf_images($sha1);
				
					if ($images)
					{
						// Grab first page image to use as thumbnail
						$image_id = '<' . $subject_id . '#image' . '>';
					
						$triples[] = $s . ' <http://schema.org/image> ' .  $image_id . ' .';						
						$triples[] = $image_id . ' <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://schema.org/ImageObject> .';
						$triples[] = $image_id . ' <http://schema.org/contentUrl> ' . '"' . addcslashes('http://bionames.org/bionames-archive/documentcloud/pages/' . $sha1 . '/1-large', '"') . '"' . ' .';
						$triples[] = $image_id . ' <http://schema.org/thumbnailUrl> ' . '"' . addcslashes('http://bionames.org/bionames-archive/documentcloud/pages/' . $sha1 . '/1-small', '"') . '"' . ' .';
								
						// Include page images in RDF (do we want to do this?)
						for ($i = 1; $i <= $images->pages; $i++)
						{
							// image
							$image_id = '<' . $subject_id . '/page#' . $i . '>';
							$triples[] = $image_id . ' <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://schema.org/ImageObject> .';

							// order 
							$triples[] = $image_id . ' <http://schema.org/position> ' . '"' . addcslashes($i, '"') . '"' . ' .';

							// URLs to images
							$triples[] = $image_id . ' <http://schema.org/contentUrl> ' . '"' . addcslashes('http://bionames.org/bionames-archive/documentcloud/pages/' . $sha1 . '/' . $i . '-large', '"') . '"' . ' .';
							$triples[] = $image_id . ' <http://schema.org/thumbnailUrl> ' . '"' . addcslashes('http://bionames.org/bionames-archive/documentcloud/pages/' . $sha1 . '/' . $i . '-small', '"') . '"' . ' .';

							// page image is part of the work
							$triples[] = $s . ' <http://schema.org/hasPart> ' .  $image_id . ' .';						
					
						}
					}
				}
			}	
		}
		
	
		if ($result->fields['PUB_TYPE'] != '')
		{
			switch ($result->fields['PUB_TYPE'])
			{
				case 'Article in Journal':
					$type = '<http://schema.org/ScholarlyArticle>';
					break;
				
				case 'Book':
					$type = '<http://schema.org/Book>';
					break;
			
				case 'Chapter in Book':
					$type = '<http://schema.org/Chapter>';
					break;

				case 'Miscellaneous':
					$type = '<http://schema.org/CreativeWork>';
					break;
				
				case 'Section in Article':
					$type = '<http://schema.org/CreativeWork>';
					break;
				
				case 'This Work':
					$type = '<http://schema.org/CreativeWork>';
					break;
				
				case 'URL':
					$type = '<http://schema.org/WebSite>';
					break;
				
				default:
					$type = '<http://schema.org/CreativeWork>';
					break;
			}
			$triples[] = $s . ' <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> ' . $type . ' .';
		}
	
		//print_r($triples);
	
	
		$t = join("\n", $triples);
	
		if (1)
		{
			echo $t . "\n";
		}
		else
		{
	
			$doc = jsonld_from_rdf($t, array('format' => 'application/nquads'));
		
			//print_r($doc);
	
			$context = (object)array(
				'@vocab' => 'http://schema.org/',
				'bibo' => 'http://purl.org/ontology/bibo/',
				'sha1' => 'http://id.loc.gov/vocabulary/preservation/cryptographicHashFunctions/sha1'
			);
	
			$compacted = jsonld_compact($doc, $context);
		
			//print_r($compacted);

			echo json_encode($compacted, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	
			echo "\n";
		}
		
		//exit();
	
		$result->MoveNext();

	}
	
	
	if ($result->NumRows() < $page)
	{
		$done = true;
	}
	else
	{
		$offset += $page;
		
		//if ($offset > 3000) { $done = true; }
	}
	

}

?>

