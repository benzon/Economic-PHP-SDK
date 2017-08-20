<?php namespace tkj\Economics\Invoice;

use tkj\Economics\ClientInterface as Client;
use tkj\Economics\Debtor\Debtor;
use Exception;
use Closure;

class Invoice
{

    /**
     * Client Connection
     * @var $client
     */
    protected $client;

    /**
     * Instance of Client
     * @var $client_raw
     */
    protected $client_raw;

    /**
     * Construct class and set dependencies
     * @param $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client->getClient();
        $this->client_raw = $client;
    }

    /**
     * Get Invoice handle by number
     * @param  integer $no
     * @return object
     */
    public function getHandle($no)
    {
        if (is_object($no) AND isset($no->Id)) return $no;

        if (@$result = $this->client
            ->Invoice_FindByNumber(array('number' => $no))
            ->Invoice_FindByNumberResult
        ) return $result;
    }

    /**
     * Get Invoices from handles
     * @param  object $handles
     * @return object
     */
    public function getArrayFromHandles($handles)
    {
        return $this->client
            ->CurrentInvoice_GetDataArray(array('entityHandles' => array('CurrentInvoiceHandle' => $handles)))
            ->CurrentInvoice_GetDataArrayResult
            ->CurrentInvoiceData;
    }

    /**
     * Get all Invoices
     * @return array
     */
    public function all()
    {
        $handles = $this->client
            ->CurrentInvoice_GetAll()
            ->CurrentInvoice_GetAllResult
            ->CurrentInvoiceHandle;

        return $this->getArrayFromHandles($handles);
    }

    /**
     * Get specific Invoice by number
     * @param  integer $no
     * @return object
     */
    public function get($no)
    {
        $handle = $this->getHandle($no);

        return $this->getArrayFromHandles($handle);
    }

    /**
     * Get Invoice due date by number
     * @param  integer $no
     * @return string
     */
    public function due($no)
    {
        $handle = $this->getHandle($no);

        return $this->client
            ->Invoice_GetDueDate(array('invoiceHandle' => $handle))
            ->Invoice_GetDueDateResult;
    }

    /**
     * Get Invoice total ny number
     * @param  integer $no
     * @param  boolean $vat
     * @return float
     */
    public function total($no, $vat = false)
    {
        $handle = $this->getHandle($no);
        $request = array('invoiceHandle' => $handle);

        if ($vat) {
            return $this->client
                ->Invoice_GetGrossAmount($request)
                ->Invoice_GetGrossAmountResult;
        }

        return $this->client
            ->Invoice_GetNetAmount($request)
            ->Invoice_GetNetAmountResult;
    }

    /**
     * Return the Invoice VAT amount
     * @param  integer $no
     * @return float
     */
    public function vat($no)
    {
        $handle = $this->getHandle($no);

        return $this->client
            ->Invoice_GetVatAmount(array('invoiceHandle' => $handle))
            ->Invoice_GetVatAmountResult;
    }

    public function lines($no)
    {
        $handle = $this->getHandle($no);

        $lineHandles = $this->client
            ->Invoice_GetLines(array('invoiceHandle' => $handle))
            ->Invoice_GetLinesResult
            ->InvoiceLineHandle;

        $line = new Line($this->client_raw);
        return $line->getArrayFromHandles($lineHandles);
    }

    /**
     * Get Invoice pdf
     * by Invoice number
     * @param  integer $no
     * @return mixed
     */
    public function pdf($no, $download = false)
    {
        $handle = $this->getHandle($no);

        $pdf = $this->client
            ->Invoice_GetPdf(array('invoiceHandle' => $handle))
            ->Invoice_GetPdfResult;

        if ($download) {
            header('Content-type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $no . '.pdf"');
            echo $pdf;
            return true;
        }

        return $pdf;
    }

    /**
     * Create a new Invoice to a specific Debtor
     * @param  integer $debtorNumber
     * @param  Closure $callback
     * @return object
     * @throws  Exception if invoiceHandle->id is  empty
     */
    public function create($debtorNumber, Closure $callback, array $options = NULL)
    {
        $debtor = new Debtor($this->client_raw);
        $debtorHandle = $debtor->getHandle($debtorNumber);

        $invoiceHandle = $this->client
            ->CurrentInvoice_Create(array('debtorHandle' => $debtorHandle))
            ->CurrentInvoice_CreateResult;


        if (!$invoiceHandle->Id) {
            throw new Exception("Error: creating Invoice.");
        }

        if ($options)
            $this->setOptions($invoiceHandle, $options);

        $this->lines = new Line($this->client_raw, $invoiceHandle);

        call_user_func($callback, $this->lines);

        return $this->client->CurrentInvoice_GetDataArray(
            array('entityHandles' => array('CurrentInvoiceHandle' => $invoiceHandle))
        )->CurrentInvoice_GetDataArrayResult;
    }

    /**
     * Set Invoice Options
     * @param mixed $handle
     * @param array $options
     */
    public function setOptions($handle, array $options)
    {
        foreach ($options as $option => $value) {
            switch (strtolower($option)) {
                case 'vat':
                    $this->client
                        ->CurrentInvoice_SetIsVatIncluded(array(
                            'currentInvoiceHandle' => $handle,
                            'value' => $value
                        ));
                    break;
                case 'text1':
                    $this->client
                        ->CurrentInvoice_SetTextLine1(array(
                            'currentInvoiceHandle' => $handle,
                            'value' => $value
                        ));
                    break;
                case 'text2':
                    $this->client
                        ->CurrentInvoice_SetTextLine2(array(
                            'currentInvoiceHandle' => $handle,
                            'value' => $value
                        ));
                    break;
                case 'heading':
                    $this->client
                        ->CurrentInvoice_SetHeading(array(
                            'currentInvoiceHandle' => $handle,
                            'value' => $value
                        ));
                    break;
                case 'termsofdelivery':
                    $this->client
                        ->CurrentInvoice_SetTermsOfDelivery(array(
                            'currentInvoiceHandle' => $handle,
                            'value' => $value
                        ));
                    break;
                case 'deliveryaddress':
                    $this->client
                        ->CurrentInvoice_SetDeliveryAddress(array(
                            'currentInvoiceHandle' => $handle,
                            'value' => $value
                        ));
                    break;
                case 'deliverycity':
                    $this->client
                        ->CurrentInvoice_SetDeliveryCity(array(
                            'currentInvoiceHandle' => $handle,
                            'value' => $value
                        ));
                    break;
                case 'deliverycountry':
                    $this->client
                        ->CurrentInvoice_SetDeliveryCountry(array(
                            'currentInvoiceHandle' => $handle,
                            'value' => $value
                        ));
                    break;
                case 'deliverypostalcode':
                    $this->client
                        ->CurrentInvoice_SetDeliveryPostalCode(array(
                            'currentInvoiceHandle' => $handle,
                            'value' => $value
                        ));
                    break;
                case 'otherreference':
                    $this->client
                        ->CurrentInvoice_SetOtherReference(array(
                            'currentInvoiceHandle' => $handle,
                            'value' => $value
                        ));
                    break;
                case 'date':
                    $this->client
                        ->CurrentInvoice_SetDate(array(
                            'currentInvoiceHandle' => $handle,
                            'value' => $value
                        ));
                    break;
                case 'layout':
                    $this->client
                        ->CurrentInvoice_SetLayout(array(
                            'currentInvoiceHandle' => $handle,
                            'value' => $value
                        ));
                    break;
            }
        }
    }

    /**
     * Book a current Invoice
     * @param  mixed $invoiceNumber
     * @return object
     */
    public function book($invoiceNumber)
    {
        $handle = $this->getHandle($invoiceNumber);

        $number = $this->client
            ->CurrentInvoice_Book(array('currentInvoiceHandle' => $handle))
            ->CurrentInvoice_BookResult;

        return $number;
    }

}
