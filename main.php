<?php
/**
 * CLI tool for converting OJS export files to DSpace Simple Archive Format folders
 * USAGE: php main.php -c config.php
 *
 * @author Toni Prieto 
 */

// Parse command line options
$options=getopt('c:I');

if(@$options['c']) 
{
	// config file specified as script parameter:
	require_once($options['c']);
} 
else 
{
	die("Missing config file : \n" .
		"USAGE: php main.php -c config.php\n");
}

//Sanity check
if (!isset($_inputFolder) || !isset($_outputFolder))
{
	die("Input or output folder don't exist\n");
}

//Get files from input folder
$xmlFiles = glob($_inputFolder . "/{*.xml,*.XML}",GLOB_BRACE);

//Sanity check: input folder has files
if (sizeof($xmlFiles) == 0)
{
	die("No files to process\n");
}

//Processing xml files
foreach($xmlFiles as $numFile => $filename)
{
	echo "***** Processing " . ($numFile + 1) . ": " . $filename . " *****\n\n";
	
	$xmlIssue = simplexml_load_file($filename) 
			or die("Can't open file $filename\n");
	
	$issueID = (string)$xmlIssue->year . "_" . (string)$xmlIssue->number . "_" . (string)$xmlIssue->volume;
	
	$numArticle = 1;
	foreach($xmlIssue->section as $section)
	{	
		foreach($section->article as $article)
		{
	
			print "*** Item " . $numArticle . " ***\n";
	
			//Add the default metadata values
			foreach($_default_values as $n => $md)
			{
				$metadata[] = $md;
			}
	
			//dc.title
			$metadata[] = array("schema" => "dc","element" => "title","value"=>(string)$article->title);
		
			//dc.descripton.abstract
			foreach($article->abstract as $abstract)
			{
				if (strcmp((string)$abstract,""))
				{
					$metadata[] = array("schema" => "dc","element" => "description","qualifier"=>"abstract","value"=>(string)$abstract);
				}
			}
			
			//dc.date.issued
			if (strcmp((string)$article->date_published,""))
			{
				$metadata[] = array("schema" => "dc","element" => "date","qualifier"=>"issued","value"=>(string)$article->date_published);
			}
			else if (strcmp((string)$xmlIssue->date_published,""))
			{
				$metadata[] = array("schema" => "dc","element" => "date","qualifier"=>"issued","value"=>(string)$xmlIssue->date_published);
			}
		
			//Number issue : local.citation.number
			if (isset($_mdNumber) && is_array($_mdNumber))
			{
				$metadata[] = array("schema" => $_mdNumber["schema"],"element" => $_mdNumber["element"],"qualifier" =>$_mdNumber["qualifier"],"value"=>(string)$xmlIssue->number);
			}

			//Volume : local.citation.volume
			if (isset($_mdVolume) && is_array($_mdVolume) && strcmp((string)$xmlIssue->volume,""))
			{
				$metadata[] = array("schema" => $_mdVolume["schema"],"element" => $_mdVolume["element"],"qualifier" =>$_mdVolume["qualifier"],"value"=>(string)$xmlIssue->volume);
			}
		
			//Volume : local.citation.volume
			if (isset($_mdKeywords) && is_array($_mdKeywords) && isset($article->indexing->subject) && strcmp((string)$article->indexing->subject,""))
			{
				$keywords = explode(",",(string)$article->indexing->subject);
				foreach($keywords as $nkey => $keyword)
				{
					$metadata[] = array("schema" => $_mdKeywords["schema"],"element" => $_mdKeywords["element"],"qualifier" =>$_mdKeywords["qualifier"],"value"=>trim($keyword));
				}
			}
				
			//dc.relation.ispartof
			if (isset($_default_publicationName))
			{	
				$ispartof = $_default_publicationName . ". " . (string)$xmlIssue->year . ", " . "Vol. " . (string)$xmlIssue->volume . ", Núm. " . (string)$xmlIssue->number;
				$metadata[] = array("schema" => "dc","element" => "relation","qualifier" =>"ispartof","value"=>trim($ispartof));	
			}	
								
			//Pages
			if (isset($article->pages) && strcmp((string)$article->pages,""))
			{
				if (strpos((string)$article->pages,"-") > 0)
				{
					$pages = explode("-",(string)$article->pages);
					$metadata[] = array("schema" => $_mdStartingPage["schema"],"element" => $_mdStartingPage["element"],"qualifier" =>$_mdStartingPage["qualifier"],"value"=>$pages[0]);
					$metadata[] = array("schema" => $_mdEndingPage["schema"],"element" => $_mdEndingPage["element"],"qualifier" =>$_mdEndingPage["qualifier"],"value"=>$pages[1]);
				
				}
				else
				{
					$metadata[] = array("schema" => $_mdStartingPage["schema"],"element" => $_mdStartingPage["element"],"qualifier" =>$_mdStartingPage["qualifier"],"value"=>(string)$article->pages);		
				}
			}
		
			//Authors
			foreach($article->author as $author)
			{
				$metadata[] = array("schema" => "dc","element" => "contributor","qualifier" =>"author","value"=>(string)$author->lastname . ", " . (string)$author->firstname);		
			}
		
			//if debug is enabled, print metadata information
			if ($_debug)
			{
				printMetadata($numArticle,$metadata);
			}
		
			//Get file information
			$files = array();
		
			if (!isset($article->galley->file))
			{
				print "WARNING: Article #" . $numArticle . " without files" . "\n\n";
			}
			else
			{
				foreach($article->galley as $galley)
				{
			
					if (isset($galley->label))
					{
						$description = (string)$galley->label;
					}
					else
					{
						$description = null;
					}
			
					foreach($galley->file as $xmlfile)
					{
						if (strcmp("base64",(string)$xmlfile->embed->attributes()->encoding))
						{
							die("Unknow encoding: " .  (string)$xmlfile->embed->attributes()->encoding ."\n");
						}
						else
						{
							$files[] = array("bundle"=>"ORIGINAL",
											"filename" => (string)$xmlfile->embed->attributes()->filename,
											"mimetype" => (string)$xmlfile->embed->attributes()->mime_type,
											"content"=>(string)$xmlfile->embed,
											"description"=>$description); 
						}	
					}
				}
			}
		
			//Add Creative Commons RDF file??
			if ($_add_creativecommons_file)
			{
				$content = file_get_contents($_default_creativecommons_rdf_file) 
					or die("Can't open creative commons rdf file: " . $_default_creativecommons_rdf_file);
				$files[] = array("bundle"=>"CC-LICENSE",
											"filename" => "license_rdf",
											"mimetype" =>"application/rdf+xml; charset=utf-8",
											"content"=>$content,
											"description"=>null); 
			}
		
		
			//if debug is enabled, print file information
			if ($_debug)
			{
				printfiles($numArticle,$files);
			}
		
			//create the DSpace SAF folder
			createDSpaceSAF($_outputFolder . "/" . $issueID . "/" ."dc" . $numArticle,$metadata,$files);	
		
			unset($files);
			unset($metadata);
		
			$numArticle++;
		}
	}
}

// Function for creating a DSpace Simple Archive Format folder
// param folderName: path where SAF is created
// param metadata: array with metadata values 
// param files: array with file information
function createDSpaceSAF($folderName,$metadata,$files)
{
	//create the item folder, recursively 	
	mkdir($folderName,0755,true)
		or die("Can't create folder " . $folderName . ". Delete it to continue if it already exists\n");

	//Get all used schemes
	$schemes = array();
	foreach($metadata as $n => $md)
	{
		if (!in_array($md["schema"],$schemes))
		{
			$schemes[] = $md["schema"];
		}
	}

	//Create a metadata file of each schema type
	foreach($schemes as $n => $schema)
	{
		//The default dublin core 
		if (!strcmp($schema,"dc"))
		{
			$filename = "dublin_core.xml";
		}
		else
		{
			$filename = "metadata_" . $schema . ".xml";
		}

		//Create metadata file
		$metadataFile =fopen($folderName . "/" . $filename,"w")
			or die("Can't create the file $folderName/$filename\n");

		fwrite($metadataFile,"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
		fwrite($metadataFile,"<dublin_core schema=\"" . $schema . "\" >\n");
		foreach($metadata as $n => $md)
		{
			if (!strcmp($schema,$md["schema"]))
			{
				fwrite($metadataFile,"\t<dcvalue ");
				fwrite($metadataFile," mdschema=\"" . $md["schema"] . "\" ");
				fwrite($metadataFile," element=\"" . $md["element"] . "\" ");
				if (isset($md["qualifier"])) 	fwrite($metadataFile," qualifier=\"" . $md["qualifier"] . "\" ");
				if (isset($md["language"])) 	fwrite($metadataFile," language=\"" . $md["language"] . "\" ");
				if (isset($md["authority"])) 	fwrite($metadataFile," authority=\"" . $md["authority"] . "\" ");
				if (isset($md["confidence"])) 	fwrite($metadataFile," confidence=\"" . $md["confidence"] . "\" ");
				fwrite($metadataFile,">");
				fwrite($metadataFile,$md["value"]);
				fwrite($metadataFile,"</dcvalue>\n");				
			}
		}
		fwrite($metadataFile,"</dublin_core>\n");

		fclose($metadataFile);
	}
	
	//Create file section
	
	//Create file "contents" of ItemImport
	$contentsFile = fopen($folderName . "/" . "contents","w")
			or die("Can't create the file $folderName/contents\n");
			
	foreach($files as $n => $fileInfo)
	{
		//put file entry
		fwrite($contentsFile,$fileInfo["filename"]);
		fwrite($contentsFile,"\t" . "bundle:" . $fileInfo["bundle"]);
		if (isset($fileInfo["description"]))	fwrite($contentsFile,"\t" . "description:" . $fileInfo["description"]);
		fwrite($contentsFile,"\n");
	
		//write file to disc
		$file = fopen($folderName . "/" . $fileInfo["filename"],"w")
			or die("Can't create the file " . $fileInfo["filename"] . "\n");
		fwrite($file, base64_decode($fileInfo["content"]));
		fclose($file);	
	}		
	fclose($contentsFile);
}

//Function for printing metadata information for debugging
function printMetadata($numArticle,$metadata)
{
	foreach($metadata as $n => $md)
	{
		$schema = $md["schema"];
		$element = $md["element"];
		if (isset($md["qualifier"])) $qualifier = $md["qualifier"];
		else						 $qualifier = null;
		$value = $md["value"];
		if (isset($md["authority"])) $authority = $md["authority"];
		else						 $authority = null;
		if (isset($md["confidence"])) 	$confidence = $md["confidence"];
		else						 	$confidence = null;

		print $schema . "." . $element . (isset($qualifier)?"." . $qualifier:"") . " = " . $value . " " . (isset($authority)?"Authority:" . $authority:"") . " " . (isset($confidence)?"Authority:" . $confidence:"") . "\n";	
	}
	print "\n";
}

//Function for printing file information
function printfiles($numArticle,$files)
{
	foreach($files as $n => $fileInfo)
	{
		//put file entry
		print "File: " . $fileInfo["filename"] . "\t" . "bundle:" . $fileInfo["bundle"] . "\t" . (isset($fileInfo["description"])?"\t" . "description:" . $fileInfo["description"]:"") . "\n";

	}
	print "\n";		
}






?>
