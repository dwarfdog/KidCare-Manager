<?php

namespace App\Tests\Unit\Course;

use App\Entity\CourseProduct;
use App\Entity\CourseReceiptLine;
use App\Service\Course\CourseReceiptParser;
use PHPUnit\Framework\TestCase;

final class CourseReceiptParserTest extends TestCase
{
    public function testParsesDateStoreTotalAndSimplePriceLines(): void
    {
        $text = <<<TXT
        Carrefour Melun
        12/03/2026
        PAIN DE MIE 2,35
        LAIT DEMI ECREME 1,15
        TOTAL 3,50
        CARTE BANCAIRE 3,50
        MERCI DE VOTRE VISITE
        TXT;

        $parser = new CourseReceiptParser();
        $result = $parser->parse($text);

        self::assertSame('Carrefour Melun', $result->storeNameGuess);
        self::assertSame('2026-03-12', $result->date?->format('Y-m-d'));
        self::assertSame(3.50, $result->total);
        self::assertCount(2, $result->lines);
        self::assertSame('PAIN DE MIE', $result->lines[0]->rawLabel);
        self::assertSame(2.35, $result->lines[0]->lineTotal);
        self::assertSame(1.0, $result->lines[0]->quantity);
        self::assertSame(CourseProduct::UNIT_PIECE, $result->lines[0]->unit);
        self::assertSame(CourseReceiptLine::CONFIDENCE_LOW, $result->lines[0]->confidence);
    }

    public function testParsesWeightedLineWithConsistentTotalAsHighConfidence(): void
    {
        $parser = new CourseReceiptParser();
        $result = $parser->parse("TOMATES GRAPPE 0,450 KG X 2,99 EUR/KG 1,35\nTOTAL 1,35");

        self::assertCount(1, $result->lines);
        $line = $result->lines[0];
        self::assertSame('TOMATES GRAPPE', $line->rawLabel);
        self::assertSame(0.450, $line->quantity);
        self::assertSame(CourseProduct::UNIT_KG, $line->unit);
        self::assertSame(2.99, $line->unitPrice);
        self::assertSame(1.35, $line->lineTotal);
        self::assertSame(CourseReceiptLine::CONFIDENCE_HIGH, $line->confidence);
    }

    public function testParsesQuantityLineWithMismatchedTotalAsMediumConfidence(): void
    {
        $parser = new CourseReceiptParser();
        $result = $parser->parse('YAOURTS NATURE 2 X 0,89 1,68');

        self::assertCount(1, $result->lines);
        $line = $result->lines[0];
        self::assertSame(2.0, $line->quantity);
        self::assertSame(0.89, $line->unitPrice);
        self::assertSame(1.68, $line->lineTotal);
        self::assertSame(CourseReceiptLine::CONFIDENCE_MEDIUM, $line->confidence);
    }

    public function testExcludesTotalTvaAndPaymentLinesFromProductDetection(): void
    {
        $parser = new CourseReceiptParser();
        $result = $parser->parse("SOUS-TOTAL 12,00\nTVA 2,40\nESPECES 12,00\nRENDU 0,00\nSIRET 123456789");

        self::assertSame([], $result->lines);
    }

    public function testReturnsNoDataForEmptyText(): void
    {
        $parser = new CourseReceiptParser();
        $result = $parser->parse('');

        self::assertNull($result->date);
        self::assertNull($result->total);
        self::assertSame([], $result->lines);
    }

    /**
     * Reproduit le format reel d'un ticket Systeme U (OmniPOS) : libelle+prix+code TVA sur une ligne,
     * confirmation "N x prix" sur la ligne suivante. Le "11"/"13" en fin de ligne libelle est un code
     * de taux de TVA, pas une quantite : il ne doit pas polluer le libelle ni etre pris pour un prix.
     */
    public function testParsesTwoLineLabelPriceWithTrailingVatCodeAndQuantityConfirmation(): void
    {
        $parser = new CourseReceiptParser();
        $result = $parser->parse("GELATINE VAHINE ST 9 FEUILLES 1,36 € 11\n1 x 1,36 EUR");

        self::assertCount(1, $result->lines);
        $line = $result->lines[0];
        self::assertSame('GELATINE VAHINE ST 9 FEUILLES', $line->rawLabel);
        self::assertSame(1.0, $line->quantity);
        self::assertSame(1.36, $line->unitPrice);
        self::assertSame(1.36, $line->lineTotal);
        self::assertSame(CourseReceiptLine::CONFIDENCE_HIGH, $line->confidence);
    }

    /**
     * Reproduit le format "pesee manuelle" sur 3 lignes de ce meme ticket : libelle+prix+code TVA,
     * marqueur "Pesée manuelle", puis le detail poids x prix/kg.
     */
    public function testParsesThreeLineWeighedItemAcrossLabelMarkerAndWeightDetail(): void
    {
        $parser = new CourseReceiptParser();
        $result = $parser->parse("TRAVERS DE PORC TEX MEX 7,72 € 11\nPesée manuelle\n0,596 kg x 12,95 €/kg");

        self::assertCount(1, $result->lines);
        $line = $result->lines[0];
        self::assertSame('TRAVERS DE PORC TEX MEX', $line->rawLabel);
        self::assertSame(0.596, $line->quantity);
        self::assertSame(CourseProduct::UNIT_KG, $line->unit);
        self::assertSame(12.95, $line->unitPrice);
        self::assertSame(7.72, $line->lineTotal);
        self::assertSame(CourseReceiptLine::CONFIDENCE_HIGH, $line->confidence);
    }

    /**
     * Le pied de ticket (detail TVA, carte de fidelite, solde carte U...) ne doit jamais produire de
     * fausses lignes produit : la detection s'arrete au premier marqueur de fin de panier.
     */
    public function testStopsProductDetectionAtTheFirstEndOfBasketMarker(): void
    {
        $parser = new CourseReceiptParser();
        $result = $parser->parse(implode("\n", [
            'PRINCE LAIT&CHOCOLAT LU PQ300G 1,56 € 11',
            '1 x 1,56 EUR',
            "Nombre de lignes d'article 11",
            '-----------',
            'TOTAL [11] Articles 55,29 €',
            'SOUS-TOTAL 51,39 €',
            'TOTAL TVA 3,90 €',
            'Dont articles éligibles TR 46,39 €',
            'BONS U (N) 5,00 €',
            'CB 50,29 €',
            '11 5,50 2,42 € 43,97 € 46,39 €',
            'VOTRE SOLDE € CARTE U PRECEDENT : 9,85 €',
        ]));

        self::assertCount(1, $result->lines);
        self::assertSame('PRINCE LAIT&CHOCOLAT LU PQ300G', $result->lines[0]->rawLabel);
        self::assertSame(55.29, $result->total);
    }

    /**
     * La date d'achat reelle (horodatage en pied de ticket, avec heure) doit primer sur une plage de
     * dates promotionnelle sans heure qui apparait plus tot dans le texte.
     */
    public function testPrefersTheTimestampedTransactionDateOverAPromotionalDateRange(): void
    {
        $parser = new CourseReceiptParser();
        $result = $parser->parse(implode("\n", [
            'Du 06-07-2026 au 02-08-2026',
            'Beneficiez de 20% en Euros Carte U',
            'Date Heure Magasin Tpv Util Tick',
            '27/07/26 17:58:43 68536 011 104 39139',
        ]));

        self::assertSame('2026-07-27', $result->date?->format('Y-m-d'));
    }
}
