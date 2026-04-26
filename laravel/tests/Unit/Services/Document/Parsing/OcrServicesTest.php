<?php

namespace Tests\Unit\Services\Document\Parsing;

use Tests\TestCase;
use App\Services\Document\Parsing\PdfScannerDetector;
use App\Services\Document\Parsing\PdfToImageRenderer;
use App\Services\Document\Ocr\OcrOrchestrator;
use App\Services\Document\Ocr\VisionOcrService;
use App\Services\Document\Ocr\TesseractOcrService;

class OcrServicesTest extends TestCase
{
    public function test_pdf_scanner_detector_can_be_instantiated(): void
    {
        $detector = new PdfScannerDetector();
        $this->assertInstanceOf(PdfScannerDetector::class, $detector);
    }

    public function test_pdf_to_image_renderer_can_be_instantiated(): void
    {
        $renderer = new PdfToImageRenderer();
        $this->assertInstanceOf(PdfToImageRenderer::class, $renderer);
    }

    public function test_ocr_orchestrator_can_be_instantiated(): void
    {
        config(['ai.ocr.enabled' => false]);
        
        $orchestrator = new OcrOrchestrator();
        $this->assertInstanceOf(OcrOrchestrator::class, $orchestrator);
    }

    public function test_ocr_orchestrator_throws_when_disabled(): void
    {
        config(['ai.ocr.enabled' => false]);
        
        $orchestrator = new OcrOrchestrator();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OCR is not enabled');
        
        $orchestrator->processScannedPdf('/non/existent/file.pdf');
    }

    public function test_vision_ocr_service_can_be_instantiated(): void
    {
        $service = new VisionOcrService();
        $this->assertInstanceOf(VisionOcrService::class, $service);
    }

    public function test_vision_ocr_service_throws_when_disabled(): void
    {
        config(['ai.vision_cascade.enabled' => false]);
        
        $service = new VisionOcrService();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Vision OCR is not enabled');
        
        $service->extractTextFromImages([['image_path' => '/non/existent.png', 'page_number' => 1]]);
    }

    public function test_vision_ocr_service_throws_when_no_nodes(): void
    {
        config(['ai.vision_cascade.enabled' => true]);
        config(['ai.vision_cascade.nodes' => []]);
        
        $service = new VisionOcrService();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No vision nodes configured');
        
        $service->extractTextFromImages([['image_path' => '/non/existent.png', 'page_number' => 1]]);
    }

    public function test_tesseract_ocr_service_can_be_instantiated(): void
    {
        $service = new TesseractOcrService();
        $this->assertInstanceOf(TesseractOcrService::class, $service);
    }

    public function test_tesseract_ocr_service_gets_system_requirements(): void
    {
        $requirements = TesseractOcrService::getSystemRequirements();
        
        $this->assertIsArray($requirements);
        $this->assertArrayHasKey('ubuntu', $requirements);
        $this->assertArrayHasKey('macos', $requirements);
        $this->assertArrayHasKey('windows', $requirements);
    }

    public function test_pdf_scanner_detector_returns_true_for_missing_file(): void
    {
        $detector = new PdfScannerDetector();
        
        $result = $detector->isScanned('/non/existent/file.pdf');
        
        $this->assertTrue($result);
    }

    public function test_pdf_scanner_detector_has_extractable_text_returns_false_for_missing_file(): void
    {
        $detector = new PdfScannerDetector();
        
        $result = $detector->hasExtractableText('/non/existent/file.pdf');
        
        $this->assertFalse($result);
    }
}