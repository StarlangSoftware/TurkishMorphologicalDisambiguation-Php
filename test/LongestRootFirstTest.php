<?php

namespace olcaytaner\test;

use olcaytaner\Corpus\Sentence;
use olcaytaner\MorphologicalAnalysis\MorphologicalAnalysis\FsmMorphologicalAnalyzer;
use olcaytaner\MorphologicalDisambiguation\LongestRootFirstDisambiguation;
use PHPUnit\Framework\TestCase;

class LongestRootFirstTest extends TestCase
{
    private LongestRootFirstDisambiguation $disambiguation;
    private FsmMorphologicalAnalyzer $fsm;

    public function setUp(): void
    {
        ini_set('memory_limit', '250M');
        $this->disambiguation = new LongestRootFirstDisambiguation();
        $this->fsm = new FsmMorphologicalAnalyzer();
    }

    public function testSentence(){
        $sentence = new Sentence("Ali topu at .");
        $parses = $this->fsm->robustMorphologicalAnalysisFromSentence($sentence);
        $correctParses = $this->disambiguation->disambiguate($parses);
    }
}