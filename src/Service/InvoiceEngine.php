<?php

namespace App\Service;

use App\Entity\Invoice;
use Easybill\ZUGFeRD2\Builder;
use Easybill\ZUGFeRD2\Model\Amount;
use Easybill\ZUGFeRD2\Model\CreditorFinancialAccount;
use Easybill\ZUGFeRD2\Model\CreditorFinancialInstitution;
use Easybill\ZUGFeRD2\Model\CrossIndustryInvoice;
use Easybill\ZUGFeRD2\Model\DateTime;
use Easybill\ZUGFeRD2\Model\DocumentContextParameter;
use Easybill\ZUGFeRD2\Model\DocumentLineDocument;
use Easybill\ZUGFeRD2\Model\ExchangedDocument;
use Easybill\ZUGFeRD2\Model\ExchangedDocumentContext;
use Easybill\ZUGFeRD2\Model\HeaderTradeAgreement;
use Easybill\ZUGFeRD2\Model\HeaderTradeSettlement;
use Easybill\ZUGFeRD2\Model\Id;
use Easybill\ZUGFeRD2\Model\LineTradeAgreement;
use Easybill\ZUGFeRD2\Model\LineTradeSettlement;
use Easybill\ZUGFeRD2\Model\Quantity;
use Easybill\ZUGFeRD2\Model\SupplyChainTradeTransaction;
use Easybill\ZUGFeRD2\Model\TaxRegistration;
use Easybill\ZUGFeRD2\Model\TradeAddress;
use Easybill\ZUGFeRD2\Model\TradeContact;
use Easybill\ZUGFeRD2\Model\TradeParty;
use Easybill\ZUGFeRD2\Model\SupplyChainTradeLineItem;
use Easybill\ZUGFeRD2\Model\TradePaymentTerms;
use Easybill\ZUGFeRD2\Model\TradePrice;
use Easybill\ZUGFeRD2\Model\TradeProduct;
use Easybill\ZUGFeRD2\Model\LineTradeDelivery;
use Easybill\ZUGFeRD2\Model\TradeSettlementHeaderMonetarySummation;
use Easybill\ZUGFeRD2\Model\TradeSettlementLineMonetarySummation;
use Easybill\ZUGFeRD2\Model\TradeSettlementPaymentMeans;
use Easybill\ZUGFeRD2\Model\TradeTax;
use Easybill\ZUGFeRD2\Model\UniversalCommunication;
use TCPDF;

class InvoiceEngine
{
    /**
     * Erzeugt das ZUGFeRD-XML (EN 16931 konform).
     */
    public function generateZugferdXml(Invoice $invoice): string
    {
        $builder = Builder::create();
        $cii = new CrossIndustryInvoice();

        // 1. ZUERST: Document Context (Das hat gefehlt!)
        // Dies definiert das Profil (z.B. BASIC, COMFORT/EN16931)
        $cii->exchangedDocumentContext = new ExchangedDocumentContext();
        // Die ID muss zum Profil passen.
        // Für EN 16931 (Comfort): urn:cen.eu:en16931:2017
        // Für BASIC: urn:zugferd.de:2p0:basic, dann auch hier anpassen: src/Service/InvoiceEngine.php:273 <fx:ConformanceLevel>EN 16931</fx:ConformanceLevel>
        $cii->exchangedDocumentContext->documentContextParameter = DocumentContextParameter::create(
            'urn:cen.eu:en16931:2017'
        );

        // 2. DANACH: ExchangedDocument (Rechnungsnummer etc.)
        $cii->exchangedDocument = new ExchangedDocument();
        $cii->exchangedDocument->id = $invoice->invoiceNumber;
        $cii->exchangedDocument->typeCode = '380';
        $cii->exchangedDocument->issueDateTime = DateTime::create(
            $invoice->createdAt->format('Ymd'),
            '102'
        );

        // Transaktion initialisieren
        $cii->supplyChainTradeTransaction = new SupplyChainTradeTransaction();
        $cii->supplyChainTradeTransaction->applicableHeaderTradeAgreement = new HeaderTradeAgreement();

        // Verkäufer
        $seller = new TradeParty();
        $seller->name = 'FiduFakt Demo';
        $cii->supplyChainTradeTransaction->applicableHeaderTradeAgreement->sellerTradeParty = $seller;

        // Käufer
        $buyer = new TradeParty();
        $buyer->name = $invoice->rawPayload['customer']['name'] ?? '';
        $buyerAddress = new TradeAddress();
        $buyerAddress->lineOne = $invoice->rawPayload['customer']['addressLineOne'] ?? '';
        $buyerAddress->lineTwo = $invoice->rawPayload['customer']['addressLineTwo'] ?? '';
        $buyerAddress->lineThree = $invoice->rawPayload['customer']['addressLineThree'] ?? '';
        $buyerAddress->postcodeCode = $invoice->rawPayload['customer']['postCode'] ?? '';
        $buyerAddress->cityName = $invoice->rawPayload['customer']['city'] ?? '';
        $buyerAddress->countryID = $invoice->rawPayload['customer']['countryCode'] ?? ''; // ISO-Code ist Pflicht
        $buyer->postalTradeAddress = $buyerAddress;
        // Kontaktinformationen (E-Mail / Telefon)
        $definedContact = new TradeContact();
        $definedContact->personName = $invoice->customerContactPerson ?? 'Buchhaltung';
        // E-Mail hinzufügen
        if (($eMail = $invoice->rawPayload['customer']['email'] ?? null) !== null) {
            $emailComm = new UniversalCommunication();
            $emailComm->uriid = Id::create($eMail);
            $definedContact->emailURIUniversalCommunication = $emailComm;
        }
        // Telefon hinzufügen
        if (($phone = $invoice->rawPayload['customer']['phone'] ?? null) !== null) {
            $phoneComm = new UniversalCommunication();
            $phoneComm->completeNumber = $phone;
            $definedContact->telephoneUniversalCommunication = $phoneComm;
        }
        $buyer->definedTradeContact = $definedContact;
        // für Unternehmen - Umsatzsteuerid
        if (($vatId = $invoice->rawPayload['customer']['vatId'] ?? null) !== null) {
            $taxRegistration = new TaxRegistration();
            $taxRegistration->id = Id::create((string)$vatId, 'VA'); // 'VA' steht für VAT
            $buyer->taxRegistrations[] = $taxRegistration;
        }
        $cii->supplyChainTradeTransaction->applicableHeaderTradeAgreement->buyerTradeParty = $buyer;

        // Rechnungsposten Loop
        $totalNet = 0.0;
        $items = $invoice->rawPayload['items'] ?? [];

        foreach ($items as $index => $itemData) {
            $lineItem = new SupplyChainTradeLineItem();

            // Zeilennummer
            $lineItem->associatedDocumentLineDocument = DocumentLineDocument::create(
                (string)($index + 1)
            );

            // Produkt
            $lineItem->specifiedTradeProduct = new TradeProduct();
            $lineItem->specifiedTradeProduct->name = $itemData['description'] ?? 'Position';

            // Menge und Einheit
            $lineItem->delivery = new LineTradeDelivery();
            $lineItem->delivery->billedQuantity = Quantity::create(
                (string)($itemData['quantity'] ?? 1),
                'C62'
            );

            // Einzelpreis
            $lineItem->tradeAgreement = new LineTradeAgreement();
            $netPrice = (float)($itemData['price'] ?? 0);
            $lineItem->tradeAgreement->netPrice = TradePrice::create(
                number_format($netPrice, 4, '.', ''),
            );

            // Zeilensumme & Steuern
            $lineItem->specifiedLineTradeSettlement = new LineTradeSettlement();
            $lineTotal = (float)($itemData['quantity'] ?? 1) * $netPrice;
            $totalNet += $lineTotal;

            $lineItem->specifiedLineTradeSettlement->monetarySummation = TradeSettlementLineMonetarySummation::create(
                number_format($lineTotal, 2, '.', '')
            );

            $lineItem->specifiedLineTradeSettlement->tradeTax[] = TradeTax::create(
                typeCode: 'VAT',
                categoryCode: 'S',
                rateApplicablePercent: '19.00'
            );

            $cii->supplyChainTradeTransaction->lineItems[] = $lineItem;
        }

        // GLOBALER SETTLEMENT BEREICH (Header)
        $headerSettlement = new HeaderTradeSettlement();
        $headerSettlement->invoiceCurrencyCode = 'EUR';
        $headerSettlement->taxCurrencyCode = 'EUR';

        // Bankdaten
        $paymentMeans = new TradeSettlementPaymentMeans();
        $paymentMeans->typeCode = '58'; // '58' ist der Code für SEPA-Überweisung (SEPA Credit Transfer)
        $paymentMeans->information = 'Überweisung';
        // Das Bankkonto (PayeePartyCreditorFinancialAccount)
        $paymentMeans->payeePartyCreditorFinancialAccount = new CreditorFinancialAccount();
        if (($iban = $invoice->rawPayload['creditor']['account']['iban'] ?? null) !== null) {
            $paymentMeans->payeePartyCreditorFinancialAccount->ibanId = Id::create((string)$iban);
        }
        // Die Bank-Kennung (PayeeSpecifiedCreditorFinancialInstitution)
        $paymentMeans->payeeSpecifiedCreditorFinancialInstitution = new CreditorFinancialInstitution();
        if (($bic = $invoice->rawPayload['creditor']['institution']['bic'] ?? null) !== null) {
            $paymentMeans->payeeSpecifiedCreditorFinancialInstitution->bicId = Id::create((string)$bic);
        }
        // Wichtig: In ein Array schieben, da mehrere Zahlungswege möglich wären
        $headerSettlement->specifiedTradeSettlementPaymentMeans[] = $paymentMeans;

        // Zahlungsziel
        $terms = new TradePaymentTerms();
        $terms->description = 'Zahlbar innerhalb von 14 Tagen ohne Abzug';
        $dueDate = (clone $invoice->createdAt)->modify('+14 days');
        $terms->dueDateDateTime = DateTime::create(
            $dueDate->format('Ymd'),
            '102'
        );
        $headerSettlement->specifiedTradePaymentTerms[] = $terms;

        // Globale Steuersumme (Pflicht für EN 16931)
        $taxAmount = $totalNet * 0.19;
        $headerTax = TradeTax::create(
            typeCode: 'VAT',
            categoryCode: 'S',
            rateApplicablePercent: '19.00'
        );
        $headerTax->basisAmount = Amount::create(number_format($totalNet, 2, '.', ''), 'EUR');
        $headerTax->calculatedAmount = Amount::create(number_format($taxAmount, 2, '.', ''), 'EUR');
        $headerSettlement->tradeTaxes[] = $headerTax;

        // Globale Summen (MonetarySummation)
        $totalGross = $totalNet + $taxAmount;
        $summation = new TradeSettlementHeaderMonetarySummation();
        $summation->lineTotalAmount = Amount::create(number_format($totalNet, 2, '.', ''), 'EUR');
        $summation->duePayableAmount = Amount::create(number_format($totalGross, 2, '.', ''), 'EUR');
        $summation->taxBasisTotalAmount = [
            Amount::create(number_format($totalNet, 2, '.', ''), 'EUR')
        ];
        $summation->taxTotalAmount = [
            Amount::create(number_format($taxAmount, 2, '.', ''), 'EUR')
        ];
        $summation->grandTotalAmount = [
            Amount::create(number_format($totalGross, 2, '.', ''), 'EUR')
        ];
        $headerSettlement->specifiedTradeSettlementHeaderMonetarySummation = $summation;

        $cii->supplyChainTradeTransaction->applicableHeaderTradeSettlement = $headerSettlement;

        return $builder->transform($cii);
    }

    /**
     * Erstellt das fertige PDF/A-3b mit eingebettetem XML.
     * Optimiert für TCPDF 6.10.1
     */
    public function createZugferdPdf(string $html, string $xml, Invoice $invoice): string
    {
        // Der 7. Parameter '3' aktiviert PDF/A-3
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false, 3);

        // Metadaten setzen
        $pdf->SetCreator('FIDUFAKT');
        $pdf->SetAuthor('FIDUFAKT');
        $pdf->SetTitle('Rechnung ' . $invoice->invoiceNumber);

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 20, 15);
        $pdf->AddPage();

        // HTML Inhalt einfügen
        $pdf->writeHTML($html);

        // XML EINBETTEN (Deine spezifische Methode)
        // Die Datei direkt in den PDF-Datenstrom einbetten
        $pdf->EmbedFileFromString('factur-x.xml', $xml);
        // ZUGFeRD XMP-Metadaten injizieren
        $pdf->setExtraXMP($this->getZugferdXmp());

        return $pdf->Output('invoice.pdf', 'S');
    }

    /**
     * Liefert das notwendige XMP-Schema für ZUGFeRD.
     */
    private function getZugferdXmp(): string
    {
        return '<rdf:Description rdf:about="" xmlns:fx="urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#">
                <fx:DocumentType>INVOICE</fx:DocumentType>
                <fx:DocumentFileName>factur-x.xml</fx:DocumentFileName>
                <fx:Version>1.0</fx:Version>
                <fx:ConformanceLevel>EN 16931</fx:ConformanceLevel>
            </rdf:Description>';
    }
}
