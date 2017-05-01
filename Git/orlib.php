<?php

//
//		CONFERENCE PORTAL PROJECT
//		VERSION 3.0.6
//
//		Copyright (c) 2010-2017 Dept. of Computer Science & Engineering,
//		Faculty of Applied Sciences, University of West Bohemia in Plzeň.
//		All rights reserved.
//
//		Code written by:	Jakub Váverka
//		Last update on:		07-Mar-2017
//		Last update by:		JV
//

//
//		DUMMY BY KE
//

require_once("tcpdf/tcpdf.php");

define("TEMP_DIR", "./offline-review"); 
define("REVIEW_FOOTER",  "<p>After filling the form in, please, upload it to the ".WEB_TITLE.
											" web review application: Go to URL ".DOC_ROOT. 
											" and after logging in, please, proceed to section My Reviews, select the corresponding submission and press the Review button.</p>"); 

class PdfWithHeader extends TCPDF {

    private $name = "";
    private $id = "";

    public function __construct($name, $id) {
        parent::__construct();
        $this->name = $name;
        $this->id = $id;
		$this->setPrintFooter(false);
    }
	
    //	page header
    public function Header() {
		$limit = 40;
		if(mb_strlen($this->name, 'UTF-8') > $limit){
			$this->name = mb_substr($this->name, 0, $limit, 'UTF-8')."... ";
		} 
		$this->Ln(10);
		$this->SetFont('freesans', 'B', 20);
		$firstWordWidth = TCPDF::GetStringWidth($this->id ." : ", 'freesans', 'B', 20, false);
		$this->Cell($firstWordWidth, 0, $this->id ." : ", 0, 0, 'L', 0, '', 0, false, 'L', 'C');
		
		$this->SetFont('freesans', 'B', 15);
        $this->Cell(0, 0, $this->name , 0, 0, 'L', 0, '', 0, false, 'L', 'C');
        $this->Line(5, $this->y + 4, $this->w - 5, $this->y + 4);
    }

}

function tempfile() {
	return tempnam(TEMP_DIR, gethostname());
}

function generate_offline_review_form($rid, $reviewer_name, $sid, $submission_name, $submission_filename, $review_html_footer = REVIEW_FOOTER) {
	$formPdf = "./offline-review/offlineform.pdf";
    $article = __DIR__."/../../".$submission_filename;
	
	
	$header = tempfile();
    $combinedWithoutHeader = tempfile();
    $withoutMeta = tempfile();
    $metafile = tempfile();
    $file =  tempfile();
	$withoutTitle = tempfile();
	$title = tempfile();
	$footerInfo = tempfile();
    $withoutInfo = tempfile();

	$limit = 35;
	if(mb_strlen($submission_name, 'UTF-8') > $limit){
		$submission_name_short= mb_substr($submission_name, 0, $limit, 'UTF-8')."... ";
	} 	
	
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	if("application/pdf" === finfo_file($finfo, $article)){
		exec('pdftk '.$formPdf.' '.$article.' cat output '.$combinedWithoutHeader);
	}else{
		copy($formPdf, $combinedWithoutHeader);
	}
	finfo_close($finfo);
	
    $pdf = new PdfWithHeader($submission_name, "REVIEW ID# ".$rid);
    $pdf->AddPage();
    $pdf->Output($header, "F");
	
    exec('pdftk '.$combinedWithoutHeader.' multistamp '.$header.' output '.$withoutTitle);
	
    unlink($header);
    unlink($combinedWithoutHeader);

	$titlePage = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
	
	$titlePage->setPrintHeader(false);
	$titlePage->setPrintFooter(false);

	$titlePage->AddPage();
	$titlePage->setFont('freesans');

	$html = "	<h1 style=\"text-align: center; font-size: 100%;\" >Offline Review Form for Submission S-ID# $sid</h1>
					<h1 style=\"text-align: center; font-size: 200%;\" >$submission_name_short</h1>
					<h1 style=\"text-align: center; font-size: 100%;\">Review by $reviewer_name</h1>";
	$border = 20;
	$titlePage->writeHTMLCell( $titlePage->GetPageWidth() - $border,0, $border/2, 20, $html, 0, 1, 0, true, 'R', true);
	
	$titlePage->AddPage();
	$titlePage->Output($title, 'F');

	exec('pdftk '.$withoutTitle.' multistamp '.$title.' output '.$withoutInfo);
	
	unlink($withoutTitle);
    unlink($title);
	
	
	$infoText = $review_html_footer;
	
	$infoPage = new TCPDF();
	$infoPage->SetFont('helvetica', '', 10);
	$infoPage->setPrintHeader(false);
	$infoPage->setPrintFooter(false);
	$infoPage->AddPage();
	$infoPage->AddPage();
	$infoPage->AddPage();
	$infoPage->writeHTMLCell(0, 0, 9 , 230 , $infoText, 0, 0, false, true, 'L', true);
	$infoPage->AddPage();
	$infoPage->Output($footerInfo, 'F');
	exec('pdftk '.$withoutInfo.' multistamp '.$footerInfo.' output '.$withoutMeta);
	
	unlink($footerInfo);
    unlink($withoutInfo);
	
	
    exec('pdftk '.$withoutMeta.' dump_data output '.$metafile);
	
    $metadata = file_get_contents($metafile);
	
	if ($metadata == FALSE) {
		unlink($withoutMeta);
		unlink($metafile);
		unlink($file);
		return FALSE;
	}
	
	
    $meta = fopen($metafile, "w");
	if ($meta == FALSE) {
		unlink($withoutMeta);
		unlink($metafile);
		unlink($file);
		return FALSE;
	}
	

    fwrite($meta, 	"InfoBegin".PHP_EOL.
							"InfoKey: RID".PHP_EOL.
							"InfoValue: $rid".PHP_EOL.
							"InfoBegin".PHP_EOL.
							"InfoKey: SID".PHP_EOL.
							"InfoValue: $sid".PHP_EOL);
    fwrite($meta, $metadata);
    exec('pdftk '.$withoutMeta.' update_info '.$metafile.' output '.$file);
	
    fclose($meta);
    unlink($withoutMeta);
    unlink($metafile);

    if (file_exists($file)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'.WEB_TITLE."_Review_Form_".$rid.".pdf".'"');

        readfile($file);
    }else{
        echo "File was not created</br>";
    }
	
	unlink($file);
	
	return TRUE;
}

function process_offline_review_form($rid, $sid, $revform_filename) {
	$outputData = tempfile();
    $metaDataFile = tempfile();

    $file_rid = "";
	$file_sid = "";
    exec('pdftk '.$revform_filename.' dump_data output '.$metaDataFile);
    $metadata = file_get_contents($metaDataFile);

	unlink($metaDataFile);
    if(strpos($metadata, "InfoKey: RID") !== FALSE) {
        $start = strpos($metadata, "InfoKey: RID") + strlen("InfoKey: RID InfoValue: ");
        $len = strpos($metadata, "InfoBegin", $start) - $start;
        $file_rid = substr($metadata, $start, $len);
    }
    if(strpos($metadata, "InfoKey: SID") !== FALSE) {
        $start = strpos($metadata, "InfoKey: SID") + strlen("InfoKey: SID InfoValue: ");
        $len = strpos($metadata, "InfoBegin", $start) - $start;
        $file_sid = substr($metadata, $start, $len);
    }

	if($file_rid == NULL){
		set_failure("review_details_fail", "<b>Unable to upload and process the offline review form</b>" .
		" for Review ID# $rid. This file is not a review form.",
			DOC_ROOT . "/index.php?form=review-details&rid=" . $rid);
			return TRUE;
	}
	if($file_rid != $rid){
		set_failure("review_details_fail", "<b>Unable to upload and process the offline review form</b>" .
		" for Review ID# $rid. This file is a review form for Review ID# $file_rid.",
			DOC_ROOT . "/index.php?form=review-details&rid=" . $rid);
			return TRUE;
	}
	if($file_sid != $sid){
		set_failure("review_details_fail", "<b>Unable to upload and process the offline review form</b>" .
		" for Review ID# $rid. This file is a review form for Submission ID# $file_sid.",
			DOC_ROOT . "/index.php?form=review-details&rid=" . $rid);
			return TRUE;
	}
	
    exec('pdftk '.$revform_filename.' dump_data_fields > '.$outputData);
    $myfile = fopen($outputData, "r");
    if (!$myfile){
		unlink($outputData);
		return FALSE;
	}
    $text = fread($myfile,filesize($outputData));
    fclose($myfile);
    unlink($outputData);

	
	$arr = array();
    $fieldPos = 0;
    while (($fieldPos = strpos($text, "---".PHP_EOL, $fieldPos))!== false) {
        $fieldPos  = $fieldPos  + strlen("---".PHP_EOL);
        $fieldSize = strpos($text, "---".PHP_EOL, $fieldPos) - strlen("---".PHP_EOL);
        $field = substr($text, $fieldPos, $fieldSize - $fieldPos);

        $fieldName = strpos($field, "FieldName: ", 0);
        if($fieldName === false){
            continue;
        }
        $fieldName += strlen("FieldName: ");
        $fieldNameSize = strpos($field, PHP_EOL, $fieldName) - $fieldName;
		
		$key = substr($field, $fieldName, $fieldNameSize);
		$arr[$key] = NULL;
        $fieldValue = strpos($field, "FieldValue: ", $fieldName);
        if($fieldValue === false){
            continue;
        }
        $fieldValue += strlen("FieldValue: ");
        $fieldValueSize = strpos($field, PHP_EOL, $fieldValue) - $fieldValue;
		
        $value = substr($field, $fieldValue, $fieldValueSize);
		
        $arr[$key] = $value;
    }

	$a = array_values($arr);
	
	if(!upload_to_DB_offline_review_form($file_rid, $a[0],$a[1], $a[2], $a[3], $a[4], $a[5], $a[6], $a[7], $a[8], $a[9], $a[10], $a[11], $a[12])){
		error("Database error", "The database server returned an error:<br />",
			DOC_ROOT . "/index.php?form=review-details&rid=" . $rid) ;
		return TRUE;
	}
	

	return TRUE;
}

function upload_to_DB_offline_review_form($rid, $originality, $significance, $relevance, $presentation, 
																	$technical_quality, $total_rating, $rewriting_amount, 
																	$reviewer_expertise, $main_contrib, $pos_aspects, 
																	$neg_aspects, $rev_comment, $int_comment){
																		
	if(!(
			($originality >= 0 && $originality <= 10) &&
			($significance >= 0 && $significance <= 10) && 
			($relevance >= 0 && $relevance <= 10) &&
			($presentation >= 0 && $presentation <= 10) && 
			($technical_quality >= 0 && $technical_quality <= 10) && 
			($total_rating >= 0 && $total_rating <= 10) &&
			($rewriting_amount >= 0 && $rewriting_amount <= 10) && 
			($reviewer_expertise >= 0 && $reviewer_expertise <= 10) &&
			!empty($main_contrib) && !empty($pos_aspects) && !empty($neg_aspects)
		)
	){
		set_failure("review_details_fail", "<b>Unable to upload and process the offline review form</b>" .
		" for Review ID# $rid, all required fields must be filled",
		DOC_ROOT . "/index.php?form=review-details&rid=" . $rid);
		return TRUE;
	}
		
	$qry = db_get('state', 'reviews', "`id`='".safe($rid)."'");
	if (!$qry){
		error("Database error", "The database server returned an error:<br />",
				DOC_ROOT . "/index.php?form=review-details&rid=" . $rid) ;
		return TRUE;
	}else if($qry === "F"){
		set_failure("review_details_fail", "<b>Unable to upload and process the offline review form</b>" .
		" for Review ID# $rid, this review is already finished",
			DOC_ROOT . "/index.php?form=review-details&rid=" . $rid);
		return TRUE;
	}
	
	
	$qstr = sprintf("UPDATE `reviews` SET `state`='U', `originality` = '".safe($originality)."', `significance` = '".safe($significance)."', `relevance` = '".safe($relevance)."', 
																	`presentation` = '".safe($presentation)."', `technical_quality` = '".safe($technical_quality)."', `total_rating` = '".safe($total_rating)."', 
																	`rewriting_amount` = '".safe($rewriting_amount)."', `reviewer_expertise` = '".safe($reviewer_expertise)."', `main_contrib` = '".safe($main_contrib)."', 
																	`pos_aspects` = '".safe($pos_aspects)."', `neg_aspects`= '".safe($neg_aspects)."', `int_comment` = '".safe($int_comment)."', 
																	`rev_comment` = '".safe($rev_comment)."' WHERE `id`='".safe($rid)."'");
		
	if(!$qstr){
		error("Database error", "<b>Unable to upload and process the offline review form</b>" .
				" for Review ID# $rid. Your review uses incompatible characters",
				DOC_ROOT . "/index.php?form=review-details&rid=" . $rid);
		return TRUE;
	}
	$qry = db_query($qstr);
	if (!$qry){
		error("Database error", "The database server returned an error:<br />",
			DOC_ROOT . "/index.php?form=review-details&rid=" . $rid) ;
		return TRUE;
	}
	
	set_message("review_details_info", "<b>Offline review form for Review ID# $rid was successfully processed</b>" ,
			DOC_ROOT . "/index.php?form=review-details&rid=" . $rid);
	return TRUE;
}

?>