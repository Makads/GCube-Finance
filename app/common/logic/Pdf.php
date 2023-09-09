<?php

namespace app\common\logic;

require_once CMF_ROOT . "vendor/tcpdf/tcpdf.php";
require_once CMF_ROOT . "vendor/tcpdf/config/tcpdf_config.php";
class Pdf
{
    private $pdfLogo = NULL;
    protected $pdf = NULL;
    protected $pdf_head = "1233";
    const PDF_LOGO = NULL;
    const PDF_LOGO_WIDTH = 10;
    const PDF_TITLE = "";
    const PDF_HEAD = "浏览器信息 操作系统信息 时间 IP ：端口";
    const PDF_FONT = "stsongstdlight";
    const PDF_FONT_STYLE = "";
    const PDF_FONT_SIZE = 10;
    const PDF_FONT_MONOSPACED = "courier";
    const PDF_IMAGE_SCALE = "1.25";
    public function __construct()
    {
        $this->pdfLogo = config("contract_get") . configuration("contract_company_logo");
        $this->pdf = new \TCPDF();
    }
    protected function setDocumentInfo($user = "", $title = "", $subject = "", $keywords = "")
    {
        if (empty($user) || empty($title)) {
            return false;
        }
        $this->pdf->SetCreator(PDF_CREATOR);
        $this->pdf->SetAuthor($user);
        $this->pdf->SetTitle($title);
        if (!empty($subject)) {
            $this->pdf->SetSubject($subject);
        }
        if (!empty($keywords)) {
            $this->pdf->SetKeywords($keywords);
        }
    }
    public function setPdfHead($pdf_head)
    {
        $this->pdf_head = $pdf_head;
    }
    protected function setHeaderFooter()
    {
        $this->pdf->SetHeaderData(self::PDF_LOGO . $this->pdfLogo, self::PDF_LOGO_WIDTH, self::PDF_TITLE, $this->pdf_head, [35, 35, 35], [221, 221, 221]);
        $this->pdf->setFooterData([35, 35, 35], [221, 221, 221]);
        $this->pdf->setHeaderFont(["stsongstdlight", self::PDF_FONT_STYLE, self::PDF_FONT_SIZE]);
        $this->pdf->setFooterFont(["helvetica", self::PDF_FONT_STYLE, self::PDF_FONT_SIZE]);
    }
    protected function closeHeaderFooter()
    {
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
    }
    protected function setMargin()
    {
        $this->pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $this->pdf->SetMargins(15, 15, 15);
        $this->pdf->SetHeaderMargin(1);
        $this->pdf->SetFooterMargin(10);
    }
    protected function setMainBody()
    {
        $this->pdf->SetAutoPageBreak(true, 25);
        $this->pdf->setImageScale(self::PDF_IMAGE_SCALE);
        $this->pdf->setFontSubsetting(true);
        $this->pdf->SetFont("stsongstdlight", "", 14, "", true);
        $this->pdf->AddPage();
    }
    public function createPDF($info = [])
    {
        if (empty($info) || !is_array($info)) {
            return false;
        }
        $this->setDocumentInfo($info["user"], $info["title"], $info["subject"], $info["keywords"]);
        if (!$info["HT"]) {
            $this->closeHeaderFooter();
        } else {
            $this->setHeaderFooter();
        }
        $this->setMargin();
        $this->setMainBody();
        $html_align = $info["html_align"] ?? "C";
        $this->pdf->writeHTML($info["content"], true, false, true, false, $html_align);
        $this->pdf->Output($info["path"], "F");
    }
    public function createPDFI($info = [])
    {
        if (empty($info) || !is_array($info)) {
            return false;
        }
        $this->setDocumentInfo($info["user"], $info["title"], $info["subject"], $info["keywords"]);
        if (!$info["HT"]) {
            $this->closeHeaderFooter();
        } else {
            $this->setHeaderFooter();
        }
        $this->setMargin();
        $this->setMainBody();
        $html_align = $info["html_align"] ?? "C";
        $this->pdf->writeHTML($info["content"], true, false, true, false, $html_align);
        $this->pdf->Output($info["path"], "I");
    }
    public function createPDFConfig($info = [])
    {
        if (empty($info) || !is_array($info)) {
            return false;
        }
        $this->setDocumentInfo($info["user"], $info["title"], $info["subject"], $info["keywords"]);
        if (!$info["HT"]) {
            $this->closeHeaderFooter();
        } else {
            $this->setHeaderFooter();
        }
        $this->setMargin();
        $this->setMainBody();
        return true;
    }
    public function getPdfObject()
    {
        return $this->pdf;
    }
    public function createSeal($filePath, $w = 50, $h = 50, $locator_data = [])
    {
        if (empty($locator_data)) {
            return false;
        }
        $tagvs = ["h1" => [["h" => 1, "n" => 3], ["h" => 1, "n" => 2]], "h2" => [["h" => 1, "n" => 2], ["h" => 1, "n" => 1]]];
        $this->pdf->setHtmlVSpace($tagvs);
        $this->pdf->setPage($locator_data["p"]);
        $this->pdf->Image($filePath, $locator_data["x"], $locator_data["y"], $w, $h);
        return true;
    }
}

?>