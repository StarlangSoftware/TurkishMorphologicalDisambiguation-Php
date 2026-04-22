<?php

namespace olcaytaner\MorphologicalDisambiguation;

use olcaytaner\NGram\NGram;

class NaiveDisambiguation extends DummyDisambiguation
{
    protected NGram $wordUniGramModel;
    protected NGram $igUniGramModel;
}