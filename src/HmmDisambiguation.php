<?php

namespace olcaytaner\MorphologicalDisambiguation;

use olcaytaner\Dictionary\Dictionary\Word;
use olcaytaner\MorphologicalAnalysis\Corpus\DisambiguatedWord;
use olcaytaner\MorphologicalAnalysis\Corpus\DisambiguationCorpus;
use olcaytaner\NGram\LaplaceSmoothing;
use olcaytaner\NGram\NGram;

class HmmDisambiguation extends NaiveDisambiguation
{
    protected NGram $wordBiGramModel;
    protected NGram $igBiGramModel;

    /**
     * The train method gets sentences from given {@link DisambiguationCorpus} and both word and the next word of that sentence at each iteration.
     * Then, adds these words together with their part of speech tags to word unigram and bigram models. It also adds the last inflectional group of
     * word to the ig unigram and bigram models.
     * <p>
     * At the end, it calculates the NGram probabilities of both word and ig unigram models by using LaplaceSmoothing, and
     * both word and ig bigram models by using InterpolatedSmoothing.
     *
     * @param DisambiguationCorpus $corpus {@link DisambiguationCorpus} to train.
     */
    public function train(DisambiguationCorpus $corpus): void
    {
        $words1 = [new Word("")];
        $igs1 = [new Word("")];
        $words2 = [new Word(""), new Word("")];
        $igs2 = [new Word(""), new Word("")];
        $this->wordUniGramModel = new NGram([], 1);
        $this->wordBiGramModel = new NGram([], 2);
        $this->igUniGramModel = new NGram([], 1);
        $this->igBiGramModel = new NGram([], 2);
        for ($i = 0; $i < $corpus->sentenceCount(); $i++) {
            $sentence = $corpus->getSentence($i);
            for ($j = 0; $j < $sentence->wordCount() - 1; $j++) {
                $word = $sentence->getWord($j);
                $nextWord = $sentence->getWord($j + 1);
                if ($word instanceof DisambiguatedWord && $nextWord instanceof DisambiguatedWord){
                    $words2[0] = $word->getParse()->getWordWithPos();
                    $words1[0] = $words2[0];
                    $words2[1] = $nextWord->getParse()->getWordWithPos();
                    $this->wordUniGramModel->addNGram($words1);
                    $this->wordBiGramModel->addNGram($words2);
                    for ($k = 0; $k < $nextWord->getParse()->size(); $k++) {
                        $igs2[0] = new Word($word->getParse()->lastInflectionalGroup()->__toString());
                        $igs2[1] = new Word($word->getParse()->getInflectionalGroup($k)->__toString());
                        $this->wordBiGramModel->addNGram($igs2);
                        $igs1[0] = $igs2[1];
                        $this->igBiGramModel->addNGram($igs1);
                    }
                }
            }
        }
        $this->wordUniGramModel->calculateNGramProbabilitiesSimple(new LaplaceSmoothing());
        $this->igUniGramModel->calculateNGramProbabilitiesSimple(new LaplaceSmoothing());
        $this->wordBiGramModel->calculateNGramProbabilitiesSimple(new LaplaceSmoothing());
        $this->igBiGramModel->calculateNGramProbabilitiesSimple(new LaplaceSmoothing());
    }

    /**
     * The disambiguate method takes {@link FsmParseList} as an input and gets one word with its part of speech tags, then gets its probability
     * from word unigram model. It also gets ig and its probability. Then, hold the logarithmic value of  the product of these probabilities in an array.
     * Also by taking into consideration the parses of these word it recalculates the probabilities and returns these parses.
     *
     * @param array $fsmParses {@link FsmParseList} to disambiguate.
     * @return array ArrayList of FsmParses.
     */
    public function disambiguate(array $fsmParses): array
    {
        if (count($fsmParses) == 0) {
            return [];
        }
        for ($i = 0; $i < count($fsmParses); $i++) {
            if ($fsmParses[$i]->size() == 0) {
                return [];
            }
        }
        $correctFsmParses = [];
        $probabilities = [];
        $best = [];
        $firstColumn = [];
        $bestColumn = [];
        for ($i = 0; $i < $fsmParses[0]->size(); $i++) {
            $currentParse = $fsmParses[0]->getFsmParse($i);
            $w1 = $currentParse->getWordWithPos();
            $probability = $this->wordUniGramModel->getProbability($w1);
            for ($j = 0; $j < $currentParse->size(); $j++) {
                $ig1 = new Word($currentParse->getInflectionalGroup($j)->toString());
                $probability *= $this->igUniGramModel->getProbability($ig1);
            }
            $firstColumn[] = log($probability);
            $bestColumn[] = -1;
        }
        $best[] = $bestColumn;
        $probabilities[] = $firstColumn;
        for ($i = 1; $i < count($fsmParses); $i++) {
            $nextColumnProbabilities = [];
            $nextBestColumn = [];
            for ($j = 0; $j < $fsmParses[$i]->size(); $j++) {
                $bestProbability = -1;
                $bestIndex = -1;
                $currentParse = $fsmParses[$i]->getFsmParse($j);
                for ($k = 0; $k < $fsmParses[$i - 1]->size(); $k++) {
                    $previousParse = $fsmParses[$i - 1]->getFsmParse($k);
                    $w1 = $previousParse->getWordWithPos();
                    $w2 = $currentParse->getWordWithPos();
                    $probability = $probabilities[$i - 1][$k] + log($this->wordBiGramModel->getProbability($w1, $w2));
                    for ($t = 0; $t < $fsmParses[$i]->getFsmParse($j)->size(); $t++) {
                        $ig1 = new Word($previousParse->lastInflectionalGroup()->toString());
                        $ig2 = new Word($currentParse->getInflectionalGroup($t)->toString());
                        $probability += log($this->igBiGramModel->getProbability($ig1, $ig2));
                    }
                    if ($probability > $bestProbability) {
                        $bestIndex = $k;
                        $bestProbability = $probability;
                    }
                }
                $nextColumnProbabilities[] = $bestProbability;
                $nextBestColumn[] = $bestIndex;
            }
            $probabilities[] = $nextColumnProbabilities;
            $best[] = $nextBestColumn;
        }
        $bestProbability = -1;
        $bestIndex = -1;
        for ($i = 0; $i < $fsmParses[count($fsmParses) - 1]->size(); $i++) {
            if ($probabilities[count($fsmParses) - 1][$i] > $bestProbability) {
                $bestProbability = $probabilities[count($fsmParses) - 1][$i];
                $bestIndex = $i;
            }
        }
        if ($bestIndex == -1) {
            return [];
        }
        $correctFsmParses[] = $fsmParses[count($fsmParses) - 1]->getFsmParse($bestIndex);
        for ($i = count($fsmParses) - 2; $i >= 0; $i--) {
            $bestIndex = $best[$i + 1][$bestIndex];
            if ($bestIndex == -1) {
                return [];
            }
            array_unshift($correctFsmParses, $fsmParses[$i]->getFsmParse($bestIndex));
        }
        return $correctFsmParses;
    }
}