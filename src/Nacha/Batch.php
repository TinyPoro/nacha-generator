<?php

namespace Nacha;

use Nacha\Record\BatchFooter;
use Nacha\Record\BatchHeader;
use Nacha\Record\Entry;

/**
 * Class Batch
 * @package Nacha
 */
class Batch
{
    // Service Class Codes
    const MIXED = 200;
    const CREDITS_ONLY = 220;
    const DEBITS_ONLY = 225;

    private $header;

    /** @var Entry[] */
    private $creditEntries = [];

    /** @var Entry[] */
    private $debitEntries = [];

    public function __construct()
    {
        $this->header = new BatchHeader();
    }

    public function getHeader()
    {
        return $this->header;
    }

    public function getTotalEntryCount()
    {
        return count($this->debitEntries) + count($this->creditEntries);
    }

    public function getTotalDebitAmount()
    {
        $amount = 0;
        foreach ($this->debitEntries as $entry) {
            $amount += (int)(string)$entry->getAmount();
        }
        return $amount;
    }

    public function getTotalCreditAmount()
    {
        $amount = 0;
        foreach ($this->creditEntries as $entry) {
            $amount += (int)(string)$entry->getAmount();
        }
        return $amount;
    }

    public function addDebitEntry(Entry $entry)
    {
        $this->debitEntries[] = $entry;
        return $this;
    }

    public function addCreditEntry(Entry $entry)
    {
        $this->creditEntries[] = $entry;
        return $this;
    }

    public function getEntryHash()
    {
        $hash = 0;
        /** @var Entry[] $entries */
        $entries = array_merge($this->debitEntries, $this->creditEntries);

        foreach ($entries as $entry) {
            $hash += $entry->getHashable();
        }

        $hashStr = substr((string)$hash, -10); // only take 10 digits from end of string to 10
        return intval($hashStr);
    }

    public function __toString()
    {
        $entries = '';

        $footer = (new BatchFooter)
            ->setEntryAddendaCount($this->getTotalEntryCount())
            ->setEntryHash($this->getEntryHash())
            ->setCompanyIdNumber((string)$this->header->getCompanyId())
            ->setOriginatingDfiId((string)$this->header->getOriginatingDFiId())
            ->setBatchNumber((string)$this->getHeader()->getBatchNumber());

        foreach ($this->debitEntries as $entry) {
            $entries .= $this->formatEntry($entry);
        }

        foreach ($this->creditEntries as $entry) {
            $entries .= $this->formatEntry($entry);
        }

        // calculate service code
        // default service code
        $this->header->setServiceClassCode(self::MIXED);
        if (count($this->debitEntries) > 0 && count($this->creditEntries) > 0) {
            $this->header->setServiceClassCode(self::MIXED);
        } elseif (count($this->debitEntries) > 0 && count($this->creditEntries) == 0) {
            $this->header->setServiceClassCode(self::DEBITS_ONLY);
        } elseif (count($this->debitEntries) == 0 && count($this->creditEntries) > 0) {
            $this->header->setServiceClassCode(self::CREDITS_ONLY);
        }


        $footer->setTotalDebitAmount($this->getTotalDebitAmount());
        $footer->setTotalCreditAmount($this->getTotalCreditAmount());
        $footer->setServiceClassCode((string)$this->header->getServiceClassCode());

        return (string)$this->header . "\n" . $entries . $footer;
    }

    private function formatEntry(Entry $entry) 
    {
        $entries = (string)$entry."\n";

        if (count($entry->getAddendas()) > 0) {
            $entries .= $this->formatAddendas($entry);
        }

        return $entries;
    }

    private function formatAddendas(Entry $entry) {
        $addendas = '';

        foreach ($entry->getAddendas() as $index => $addenda) {
            $addenda->setAddendaSequenceNumber($index+1); // offset for 0 index
            $addenda->setEntryDetailSequenceNumber($entry->getTraceNumber());

            $addendas .= (string)$addenda."\n";
        }

        return $addendas;
    }
}
