<?php

namespace Magenta\Bundle\SWarrantyAdminBundle\Admin\Customer;

use Magenta\Bundle\SWarrantyAdminBundle\Admin\BaseCRUDAdminController;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Customer\CaseAppointment;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Customer\Warranty;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Customer\WarrantyCase;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Product\Brand;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Product\Product;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Product\ServiceZone;
use Magenta\Bundle\SWarrantyModelBundle\Service\SpreadsheetWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class WarrantyCaseAdminController extends BaseCRUDAdminController
{
    public function listAction()
    {
        $this->admin->setTemplate('base_list', '@MagentaSWarrantyAdmin/Admin/Customer/WarrantyCase/CRUD/list.html.twig');
        $this->admin->setTemplate('base_list_field', '@MagentaSWarrantyAdmin/Admin/Customer/WarrantyCase/CRUD/list_field.html.twig');
        $this->admin->setTemplate('button_create', '@MagentaSWarrantyAdmin/empty.html.twig');

        return parent::listAction();
    }

    /**
     * @param ProxyQueryInterface $selectedModelQuery
     * @param Request             $request
     *
     * @return RedirectResponse
     */
    public function batchActionServiceSheet(ProxyQueryInterface $selectedModelQuery, Request $request = null)
    {
//        $this->admin->checkAccess('edit');
//        $this->admin->checkAccess('delete');

        $modelManager = $this->admin->getModelManager();

        $selectedModels = $selectedModelQuery->execute();

        // do the merge work here

        try {
            $d = new \DateTime();

            $cases = [];
            /** @var WarrantyCase $case */
            foreach ($selectedModels as $case) {
                $cases[] = $case->getId();
            }

            $htmlDisplayUrl = $this->get('router')->generate('service_sheet', [
                'cases' => $cases,
            ], RouterInterface::ABSOLUTE_URL);
            $response = new RedirectResponse($this->getParameter('PDF_API_BASE_URL').$this->getParameter('PDF_DOWNLOAD_PREFIX').str_replace('?', '%3F', $htmlDisplayUrl).'/'.'service-sheets_'.$d->format('d-m-Y'));

            return $response;
        } catch (\Exception $e) {
            $this->addFlash('sonata_flash_error', 'flash_batch_merge_error '.$e->getMessage().' -> '.$e->getTraceAsString());

            return new RedirectResponse(
                $this->admin->generateUrl('list', [
                    'filter' => $this->admin->getFilterParameters(),
                ])
            );
        }

        $this->addFlash('sonata_flash_success', 'flash_batch_merge_success');

        return new RedirectResponse(
            $this->admin->generateUrl('list', [
                'filter' => $this->admin->getFilterParameters(),
            ])
        );
    }

    /**
     * Export data to specified format.
     *
     * @param Request $request
     *
     * @return Response
     *
     * @throws AccessDeniedException If access is not granted
     * @throws \RuntimeException     If the export format is invalid
     */
    public function exportAction(Request $request)
    {
        /**
         * @var ContainerInterface
         */
        $c = $this->container;

        //		$this->admin->checkAccess('export');
        $format = $request->get('format');

        $adminExporter = $this->get('sonata.admin.admin_exporter');
        $allowedExportFormats = array_merge($adminExporter->getAvailableFormats($this->admin), ['pdf']);
        //		$filename             = $adminExporter->getExportFilename($this->admin, $format);
        //		$exporter             = $this->get('sonata.exporter.exporter');

        if (!in_array($format, $allowedExportFormats)) {
            throw new \RuntimeException(
                sprintf(
                    'Export in format `%s` is not allowed for class: `%s`. Allowed formats are: `%s`',
                    $format,
                    $this->admin->getClass(),
                    implode(', ', $allowedExportFormats)
                )
            );
        }

        if ('xls' === $format) {
            $format = 'xlsx';
        }
        $filename = sprintf(
            'export_%s_%s.%s',
            strtolower(substr($this->admin->getClass(), strripos($this->admin->getClass(), '\\') + 1)),
            date('Y_m_d_H_i_s', strtotime('now')),
            $format
        );

        if ('pdf' === $format) {
            $d = new \DateTime();

            $source = $this->admin->getDataSourceIterator();
            $cases = [];
            /** @var array of WarrantyCase $case */
            foreach ($source as $case) {
                $cases[] = $case['id'];
            }

            $htmlDisplayUrl = $c->get('router')->generate('service_sheet', [
                'cases' => $cases,
            ], RouterInterface::ABSOLUTE_URL);
            $pdfUrl = $c->getParameter('PDF_API_BASE_URL').$c->getParameter('PDF_DOWNLOAD_PREFIX').str_replace('?', '%3F', $htmlDisplayUrl).'/'.'service-sheets_'.$d->format('d-m-Y');
            $response = new RedirectResponse($pdfUrl);

            return $response;
        } elseif ('xlsx' === $format) {
            // ask the service for a Excel5
            $phpExcelObject = new Spreadsheet();

            $phpExcelObject->getProperties()->setCreator('Solution')
                ->setLastModifiedBy('Solution')
                ->setTitle('Download - Raw Data')
                ->setSubject('Order Item - Raw Data')
                ->setDescription('Raw Data')
                ->setKeywords('office 2005 openxml php')
                ->setCategory('Raw Data Download');

            $phpExcelObject->setActiveSheetIndex(0);
            $activeSheet = $phpExcelObject->getActiveSheet();

            //			$activeSheet
            //				->setCellValue('A1', "ID")
            //				->setCellValue('B1', "Number")
            //				->setCellValue('C1', "Priority")
            //				->setCellValue('D1', "Date Created")
            //				->setCellValue('E1', "Date Closed")
            //				->setCellValue('F1', "Product Brand")
            //				->setCellValue('G1', "Product Category")
            //				->setCellValue('H1', "Product Model Number")
            //			Product Serial NUmbers, Date of DElivery,
            //				->setCellValue('I1', "Case Description")
            //				->setCellValue('J1', "Service Notes")
            //				->setCellValue('K1', "Special Notes")
            //				->setCellValue('L1', "Technicians")
            //				->setCellValue('M1', "Service Zones")
            //				->setCellValue('N1', "Customer Name")//			            ->setCellValue('O1', "Technician Name(s)")
            //				->setCellValue('O1', "Customer Unit/Block")
            //				->setCellValue('P1', "Customer Address")
            //				->setCellValue('Q1', "Customer Contact Number")
            //				6.2 In the excel downoad file we also need to have the Customer's Address (all 3 lines),  contact number

            // Set active sheet index to the first sheet, so Excel opens this as the first sheet
            $phpExcelObject->setActiveSheetIndex(0);
            $activeSheet = $phpExcelObject->getActiveSheet();
            $sWriter = new SpreadsheetWriter($activeSheet);
            $sWriter->goFirstColumn();
            $sWriter->goFirstRow();

            $source = $this->admin->getDataSourceIterator();
            $repo = $this->getDoctrine()->getRepository(WarrantyCase::class);
            $sWriter->goFirstColumn();
            $sWriter->goFirstRow();
            $sWriter
                ->writeCellAndGoRight('ID')
                ->writeCellAndGoRight('Number')
                ->writeCellAndGoRight('Priority')
                ->writeCellAndGoRight('Date Created')
                ->writeCellAndGoRight('Date Closed')
                ->writeCellAndGoRight('Product Brand')
                ->writeCellAndGoRight('Product Category')
                ->writeCellAndGoRight('Product Model Number')
                ->writeCellAndGoRight('Product Serial Numbers')
                ->writeCellAndGoRight('Date of Delivery')
                ->writeCellAndGoRight('Case Description')
                ->writeCellAndGoRight('Service Notes')
                ->writeCellAndGoRight('Special Notes')
                ->writeCellAndGoRight('Total Amount Collected')
                ->writeCellAndGoRight('Parts Replaced')
                ->writeCellAndGoRight('Product Issue')
                ->writeCellAndGoRight('Technicians')
                ->writeCellAndGoRight('Service Zones')
                ->writeCellAndGoRight('Customer Name')
                ->writeCellAndGoRight('Unit/Block')
                ->writeCellAndGoRight('Customer Address')
                ->writeCellAndGoRight('Contact Number');

            $sWriter->goLeft();

            foreach ($source as $data) {
                $case = $repo->find($data['id']);
                $sWriter->goDown();
                $sWriter->goFirstColumn();

                $sWriter->writeCellAndGoRight($case->getId());
                // Number
                $sWriter->writeCellAndGoRight($case->getNumber());
                $sWriter->writeCellAndGoRight($case->getPriority());
                $sWriter->writeCellAndGoRight($case->getCreatedAt()->format('d-m-Y'));
                $closedAtStr = '';
                if (!empty($case->getClosedAt())) {
                    $closedAtStr = $case->getClosedAt()->format('d-m-Y');
                }
                $sWriter->writeCellAndGoRight($closedAtStr);
                $brand = '';
                $category = '';
                $modelNumber = '';
                $zoneStr = '';
                $customerName = '';
                $serialNumber = '';
                $purchaseDate = '';
                $totalAmountCollected = 0;
                $partsReplaced = '';
                $productIssue = '';

                /** @var Warranty $w */
                if (!empty($w = $case->getWarranty())) {
                    /** @var Product $p */
                    if (!empty($p = $w->getProduct())) {
                        /** @var Brand $b */
                        if (!empty($b = $p->getBrand())) {
                            $brand = $b->getName();
                        }
                        if (!empty($c = $p->getCategory())) {
                            $category = $c->getName();
                        }
                        $modelNumber = ''.$p->getModelNumber();
                    }

                    $serialNumber = ''.$w->getProductSerialNumber();
                    if (!empty($dop = $w->getPurchaseDate())) {
                        $purchaseDate = $dop->format('d - m - Y');
                    }
                    if (!empty($customer = $w->getCustomer())) {
                        $customerName = $customer->getName();
                    }
                }

                $apmts = $case->getAppointments();
                if ($apmts->count() > 0) {
                    /** @var CaseAppointment $apmt */
                    foreach ($apmts as $apmt) {
                        $totalAmountCollected += $apmt->getAmountCollected();
                        if (!empty($_partsReplaced = $apmt->getPartsReplaced())) {
                            $partsReplaced .= ', '.$_partsReplaced;
                        }
                        if (!empty($_productIssue = $apmt->getPartsReplaced())) {
                            $productIssue .= ', '.$_productIssue;
                        }
                    }
                }

                /** @var ServiceZone $zone */
                if (!empty($zone = $case->getServiceZone())) {
                    $zoneStr = $zone->getName();
                }

                $sWriter->writeCellAndGoRight($brand);
                $sWriter->writeCellAndGoRight($category);
                $sWriter->writeCellAndGoRight($modelNumber);
                $sWriter->writeCellAndGoRight($serialNumber);
                $sWriter->writeCellAndGoRight($purchaseDate);
                $sWriter->writeCellAndGoRight(!empty($desc = $case->getDescription()) ? strip_tags($desc) : '');
                $sWriter->writeCellAndGoRight(!empty($note = $case->getServiceNotesString()) ? strip_tags($note) : '');
                $sWriter->writeCellAndGoRight(!empty($remarks = $case->getSpecialRemarks()) ? strip_tags($remarks) : '');

                $sWriter->writeCellAndGoRight($totalAmountCollected);
                $sWriter->writeCellAndGoRight($partsReplaced);
                $sWriter->writeCellAndGoRight($productIssue);

                $sWriter->writeCellAndGoRight($case->getAssigneeString());
                $sWriter->writeCellAndGoRight($zoneStr);
                $sWriter->writeCellAndGoRight($customerName);
                $sWriter->writeCellAndGoRight($customer->getAddressUnitNumber());
                $sWriter->writeCellAndGoRight($customer->getHomeAddress());
                $sWriter->writeCellAndGoRight($customer->getTelephone());
            }

            // create the writer
            $writer = new Xlsx($phpExcelObject);
            //			$writer = $this->get('phpexcel')->createWriter($phpExcelObject, 'Excel5');
            // create the response
            //			$response = $this->get('phpexcel')->createStreamedResponse($writer);
            $response = new StreamedResponse(
                function () use ($writer) {
                    $writer->save('php://output');
                },
                200,
                []
            );

            $response->headers->set('Content-Type', 'text/vnd.ms-excel; charset=utf-8');
            $response->headers->set('Pragma', 'public');
            $response->headers->set('Cache-Control', 'maxage=1');

            $response->headers->set('Content-Disposition', 'attachment;filename='.$filename);

            return $response;
        }

        return parent::exportAction($request);
    }

    protected function getDecision(
        $action
    ): ?string {
        $d = parent::getDecision($action);
        if (empty($d)) {
            $d = strtoupper($action);
        }

        return $d;
    }

    protected function preRenderDecision(
        $action, $object
    ) {
        if ('show' !== $action) {
            return $this->redirect($this->admin->generateObjectUrl('edit', $object, []));
        }
    }
}
