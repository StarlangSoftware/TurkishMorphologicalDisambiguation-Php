<?php

namespace olcaytaner\MorphologicalDisambiguation;

use olcaytaner\MorphologicalAnalysis\Corpus\DisambiguationCorpus;
use olcaytaner\MorphologicalAnalysis\MorphologicalAnalysis\FsmParseList;
use olcaytaner\Util\FileUtils;

class LongestRootFirstDisambiguation extends MorphologicalDisambiguator
{
    private array $rootList;

    /**
     * Constructor for the longest root first disambiguation algorithm. The method reads a list of (surface form, most
     * frequent root word for that surface form) pairs from a given file.
     * @param string $fileName File that contains list of (surface form, most frequent root word for that surface form) pairs.
     */
    public function __construct(string $fileName = "../rootlist.txt")
    {
        $this->readFromFile($fileName);
    }

    /**
     * Reads the list of (surface form, most frequent root word for that surface form) pairs from a given file.
     * @param string $fileName Input file name.
     */
    private function readFromFile(string $fileName): void{
        $this->rootList = FileUtils::readHashMap($fileName);
    }

    /**
     * Train method implements method in {@link MorphologicalDisambiguator}.
     *
     * @param DisambiguationCorpus $corpus {@link DisambiguationCorpus} to train.
     */
    public function train(DisambiguationCorpus $corpus): void
    {
    }

    /**
     * The disambiguate method gets an array of fsmParses. Then loops through that parses and finds the longest root
     * word. At the end, gets the parse with longest word among the fsmParses and adds it to the correctFsmParses
     * {@link ArrayList}.
     *
     * @param array $fsmParses {@link FsmParseList} to disambiguate.
     * @return array correctFsmParses {@link ArrayList} which holds the parses with longest root words.
     */
    public function disambiguate(array $fsmParses): array
    {
        $correctFsmParses = [];
        $i = 0;
        foreach ($fsmParses as $fsmParseList) {
            $surfaceForm = $fsmParseList->getFsmParse(0)->getSurfaceForm();
            if (!array_key_exists($surfaceForm, $this->rootList)){
                $bestRoot = null;
            } else {
                $bestRoot = $this->rootList[$surfaceForm];
            }
            $rootFound = false;
            for ($j = 0; $j < $fsmParseList->size(); $j++) {
                if ($fsmParseList->getFsmParse($j)->getWord()->getName() == $bestRoot) {
                    $rootFound = true;
                    break;
                }
            }
            if ($bestRoot == null || !$rootFound){
                $bestParse = $fsmParseList->getParseWithLongestRootWord();
                $fsmParseList->reduceToParsesWithSameRoot($bestParse->getWord()->getName());
            } else {
                $fsmParseList->reduceToParsesWithSameRoot($bestRoot);
            }
            $newBestParse = AutoDisambiguator::caseDisambiguator($i, $fsmParses, $correctFsmParses);
            if ($newBestParse != null){
                $bestParse = $newBestParse;
            } else {
                $bestParse = $fsmParseList->getFsmParse(0);
            }
            $correctFsmParses[] = $bestParse;
            $i++;
        }
        return $correctFsmParses;
    }
}