OJSXMLIssuestoDSpaceSAF
==============

CLI tool for converting exported OJS Issues files to DSpace Simple Archive Format (ItemImport tool)

1) Export Issues from OJS [1] (one file for each journal issue) and save it into the input folder
2) Costumize your config.php file (define default metadata and metadata fields for citation fields)
3) Execute the php cli

 php main.php -c config.php

4) The output folder should contain folder and files ready to be imported with DSpace ItemImport utility [2]


[1] https://pkp.sfu.ca/wiki/index.php/Importing_and_Exporting_Data#Exporting_Articles_and_Issues_From_the_Web

[2] https://wiki.duraspace.org/display/DSDOC5x/Importing+and+Exporting+Items+via+Simple+Archive+Format
