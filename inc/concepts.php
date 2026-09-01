<?php
/**
 * Thematic similarity: "what is this actually about?"
 *
 * Cast, genre and franchise all miss the connection a person makes when they
 * say Prison Break should lead you to Now You See Me, White Collar and
 * Breakout Kings. None of those share a cast, a creator or a franchise, and
 * two of them are not even the same medium. What they share is an idea:
 * clever people running elaborate schemes around the law.
 *
 * Two layers capture that here.
 *
 * 1. Keywords as evidence. TMDB tags every title with keywords, and asking
 *    /discover for one keyword at a time turns each keyword into a bucket of
 *    titles. A candidate's overlap with the subject is then just the buckets
 *    it turns up in — and because each response reports how many titles carry
 *    that keyword, rare tags ("escaped prisoner") outweigh common ones
 *    ("brother") automatically. That is IDF, for free.
 *
 * 2. A concept map for the leaps keywords cannot make. "Escape" and "heist"
 *    are different tags but neighbouring ideas, so titles are also placed in
 *    a small space of concepts, scored with a soft cosine that credits
 *    related concepts, not only identical ones. This is the layer that
 *    encodes the associations a person would make out loud.
 */

/** Concepts, and the words (in keywords or overviews) that signal them. */
function concept_taxonomy() {
    return [
        'escape' => ['prison', 'prisoner', 'prisoners', 'escape', 'escaped', 'escaping', 'escapee', 'breakout', 'jail', 'inmate', 'convict', 'con', 'fugitive', 'manhunt', 'captivity', 'imprisonment', 'penitentiary', 'incarcerated', 'wrongful', 'innocent', 'sentence', 'guard'],
        'heist' => ['heist', 'robbery', 'rob', 'robber', 'thief', 'theft', 'steal', 'stealing', 'stolen', 'burglar', 'burglary', 'vault', 'bank', 'safe', 'loot', 'diamond', 'forger', 'forgery', 'counterfeit', 'smuggle'],
        'con' => ['con', 'conman', 'scam', 'swindle', 'swindler', 'fraud', 'hustler', 'impostor', 'deception', 'deceive', 'trick', 'illusion', 'illusionist', 'magician', 'magic', 'disguise', 'identity', 'charming'],
        'mastermind' => ['mastermind', 'genius', 'plan', 'planning', 'scheme', 'plot', 'elaborate', 'ingenious', 'outsmart', 'puzzle', 'strategy', 'expertise', 'brilliant', 'engineer', 'mission'],
        'crew' => ['team', 'crew', 'gang', 'squad', 'partner', 'partners', 'recruit', 'recruits', 'assemble', 'unit', 'task', 'force', 'colleague', 'consultant'],
        'law' => ['fbi', 'cia', 'police', 'detective', 'marshal', 'marshals', 'agent', 'agents', 'investigator', 'investigation', 'cop', 'cops', 'interpol', 'undercover', 'sheriff', 'enforcement', 'bureau'],
        'conspiracy' => ['conspiracy', 'coverup', 'corruption', 'corrupt', 'framed', 'frame', 'whistleblower', 'government', 'secret', 'betrayal', 'setup', 'political'],
        'crime' => ['criminal', 'criminals', 'crime', 'mob', 'mafia', 'cartel', 'gangster', 'syndicate', 'kingpin', 'underworld', 'drug', 'drugs', 'smuggling', 'murder'],
        'family' => ['brother', 'brothers', 'sister', 'father', 'mother', 'son', 'daughter', 'family', 'sibling', 'siblings'],
        'survival' => ['survival', 'survive', 'survivor', 'stranded', 'wilderness', 'hunted', 'trapped'],
        'revenge' => ['revenge', 'vengeance', 'retribution', 'payback', 'avenge', 'vendetta'],
        'spy' => ['spy', 'spies', 'espionage', 'mole', 'infiltrate', 'covert', 'intelligence', 'operative'],
        'legal' => ['lawyer', 'attorney', 'trial', 'court', 'courtroom', 'verdict', 'justice', 'appeal', 'defendant'],
        'tech' => ['hacker', 'hacking', 'hack', 'computer', 'cyber', 'surveillance', 'code'],
    ];
}

/** Human-readable label per concept, used in the "why" line on a card. */
function concept_labels() {
    return [
        'escape' => 'escape & fugitives',
        'heist' => 'heists & robbery',
        'con' => 'cons & deception',
        'mastermind' => 'elaborate plans',
        'crew' => 'a crew on a job',
        'law' => 'law enforcement',
        'conspiracy' => 'conspiracy & cover-ups',
        'crime' => 'organised crime',
        'family' => 'family bonds',
        'survival' => 'survival',
        'revenge' => 'revenge',
        'spy' => 'espionage',
        'legal' => 'courtroom & justice',
        'tech' => 'hacking & surveillance',
    ];
}

/**
 * How close two concepts feel to a person. This is the "Reddit intuition"
 * layer: nobody tags Prison Break as a heist story, but anyone who likes one
 * tends to like the other, so the two concepts credit each other.
 */
function concept_affinity() {
    static $pairs = [
        'escape|heist' => 0.55, 'escape|con' => 0.45, 'escape|crime' => 0.50,
        'escape|law' => 0.50, 'escape|conspiracy' => 0.50, 'escape|mastermind' => 0.50,
        'escape|crew' => 0.35, 'escape|survival' => 0.45, 'escape|legal' => 0.40,
        'escape|family' => 0.30,
        'heist|con' => 0.80, 'heist|mastermind' => 0.75, 'heist|crew' => 0.65,
        'heist|law' => 0.55, 'heist|crime' => 0.60, 'heist|tech' => 0.40,
        'con|mastermind' => 0.70, 'con|law' => 0.50, 'con|crime' => 0.50, 'con|crew' => 0.40,
        'mastermind|crew' => 0.50, 'mastermind|conspiracy' => 0.50, 'mastermind|law' => 0.35,
        'mastermind|spy' => 0.50, 'mastermind|tech' => 0.45,
        'law|crime' => 0.60, 'law|conspiracy' => 0.50, 'law|crew' => 0.45,
        'law|spy' => 0.55, 'law|legal' => 0.60,
        'crime|conspiracy' => 0.50, 'crime|crew' => 0.45, 'crime|revenge' => 0.40,
        'spy|conspiracy' => 0.60, 'spy|tech' => 0.45,
        'legal|conspiracy' => 0.45,
    ];
    return $pairs;
}

/**
 * Canonical TMDB keyword ids per concept, resolved once from
 * /search/keyword and baked in so no lookup round trip is needed.
 *
 * These drive the bridge: when a title is about escape, its neighbouring
 * concepts (heists, cons, elaborate plans) are queried too, which is how a
 * prison-break show reaches a heist film that shares none of its own tags.
 */
function concept_anchor_keywords() {
    return [
        'escape' => [9777 /* prison escape */, 169271 /* escaped prisoner */, 156121 /* ex-con */, 10718 /* fugitive */],
        'heist' => [10051 /* heist */, 15363 /* bank robbery */, 156031 /* art theft */, 158992 /* gentleman thief */],
        'con' => [3202 /* con man */, 10453 /* con artist */, 11454 /* scam */, 1568 /* undercover */],
        'mastermind' => [18023 /* criminal mastermind */, 251794 /* master plan */, 6257 /* genius */],
        'crew' => [15186 /* task force */, 212513 /* criminal consultant */],
        'law' => [1812 /* fbi */, 11207 /* u.s. marshal */, 15167 /* police detective */],
        'conspiracy' => [10410 /* conspiracy */, 181635 /* government conspiracy */, 9665 /* cover-up */],
        'crime' => [10291 /* organized crime */, 10391 /* mafia */, 10175 /* drug cartel */],
        'spy' => [5265 /* espionage */, 470 /* spy */],
        'legal' => [12662 /* wrongful imprisonment */, 33519 /* courtroom */, 10909 /* lawyer */],
        'tech' => [2157 /* hacker */, 12361 /* hacking */],
        'revenge' => [9748 /* revenge */],
        'survival' => [10349 /* survival */],
        'family' => [5301 /* brother */, 18035 /* family */],
    ];
}

/** [keyword id => name] for the anchors above, for the "why" line on a card. */
function concept_anchor_names() {
    return [
        9777 => 'prison escape', 169271 => 'escaped prisoner', 156121 => 'ex-con', 10718 => 'fugitive',
        10051 => 'heist', 15363 => 'bank robbery', 156031 => 'art theft', 158992 => 'gentleman thief',
        3202 => 'con man', 10453 => 'con artist', 11454 => 'scam', 1568 => 'undercover',
        18023 => 'criminal mastermind', 251794 => 'master plan', 6257 => 'genius',
        15186 => 'task force', 212513 => 'criminal consultant',
        1812 => 'FBI', 11207 => 'U.S. marshal', 15167 => 'police detective',
        10410 => 'conspiracy', 181635 => 'government conspiracy', 9665 => 'cover-up',
        10291 => 'organized crime', 10391 => 'mafia', 10175 => 'drug cartel',
        5265 => 'espionage', 470 => 'spy',
        12662 => 'wrongful imprisonment', 33519 => 'courtroom', 10909 => 'lawyer',
        2157 => 'hacker', 12361 => 'hacking',
        9748 => 'revenge', 10349 => 'survival', 5301 => 'brother', 18035 => 'family',
    ];
}

/**
 * Spread a concept profile one hop across the affinity graph, so neighbouring
 * ideas are worth searching even when the subject was never tagged with them.
 * Returns [concept => weight], strongest first.
 */
function concept_neighbours(array $profile, $limit = 3) {
    $spread = [];
    foreach ($profile as $concept => $weight) {
        foreach (array_keys(concept_taxonomy()) as $other) {
            if ($other === $concept || isset($profile[$other])) continue;
            $affinity = concept_similarity_weight($concept, $other);
            if ($affinity <= 0) continue;
            $spread[$other] = ($spread[$other] ?? 0) + $weight * $affinity;
        }
    }
    arsort($spread);
    return array_slice($spread, 0, $limit, true);
}

function concept_similarity_weight($a, $b) {
    if ($a === $b) return 1.0;
    $pairs = concept_affinity();
    $key = $a < $b ? "$a|$b" : "$b|$a";
    return $pairs[$key] ?? 0.0;
}

/** Words carrying no thematic signal. */
function text_stopwords() {
    static $words = null;
    if ($words === null) {
        $words = array_flip(explode(' ', 'the a an and or but of to in on at by for with from into over after before is are was were be been being his her its their our your my he she they them it this that these those who whom which what when where while as if then than so too very can will just dont doesnt not no nor own same all any both each few more most other some such only own how why about against between during through above below up down out off again further once here there both new own also has have had do does did get got make made take took come came go goes going one two three first second next last back man woman men women life story world time year years day days'));
    }
    return $words;
}

/** Lowercase content words, light plural trim, stopwords and short words out. */
function text_tokens($text) {
    $text = strtolower((string) $text);
    $text = preg_replace('/[^a-z0-9\s]+/', ' ', $text);
    $stop = text_stopwords();
    $tokens = [];
    foreach (preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) as $word) {
        if (strlen($word) < 3 || isset($stop[$word])) continue;
        // Crude singularisation so "prisoners" and "prisoner" agree.
        if (strlen($word) > 4 && substr($word, -1) === 's' && substr($word, -2) !== 'ss') {
            $word = substr($word, 0, -1);
        }
        $tokens[] = $word;
    }
    return $tokens;
}

/**
 * Concept weights for a title, from any mix of keyword names and overview
 * text. Returns a normalised [concept => weight] vector.
 */
function concept_profile(array $tokens) {
    $taxonomy = concept_taxonomy();
    $profile = [];
    $counts = array_count_values($tokens);

    foreach ($taxonomy as $concept => $terms) {
        $hits = 0;
        foreach ($terms as $term) {
            if (isset($counts[$term])) $hits += min($counts[$term], 3);
        }
        // Diminishing returns: the fifth "prison" says little the first didn't.
        if ($hits > 0) $profile[$concept] = sqrt($hits);
    }

    $norm = 0.0;
    foreach ($profile as $weight) $norm += $weight * $weight;
    if ($norm > 0) {
        $norm = sqrt($norm);
        foreach ($profile as $concept => $weight) $profile[$concept] = $weight / $norm;
    }
    return $profile;
}

/**
 * Soft cosine similarity: like cosine, but concepts that merely resemble each
 * other still contribute, weighted by concept_affinity(). Without it a heist
 * story and a prison-break story score zero against each other.
 */
function concept_soft_cosine(array $a, array $b) {
    if (!$a || !$b) return 0.0;

    $dot = function ($x, $y) {
        $sum = 0.0;
        foreach ($x as $ca => $wa) {
            foreach ($y as $cb => $wb) {
                $weight = concept_similarity_weight($ca, $cb);
                if ($weight > 0) $sum += $wa * $wb * $weight;
            }
        }
        return $sum;
    };

    $denominator = sqrt($dot($a, $a)) * sqrt($dot($b, $b));
    return $denominator > 0 ? $dot($a, $b) / $denominator : 0.0;
}

/**
 * IDF-weighted overlap between two token lists, using document frequencies
 * measured across the candidate pool itself.
 */
function text_overlap_score(array $subject_tokens, array $candidate_tokens, array $document_frequency, $documents) {
    if (!$subject_tokens || !$candidate_tokens) return 0.0;

    $subject = array_unique($subject_tokens);
    $candidate = array_flip($candidate_tokens);
    $score = 0.0;
    $total = 0.0;

    foreach ($subject as $token) {
        $frequency = $document_frequency[$token] ?? 1;
        $idf = log(($documents + 1) / $frequency);
        $total += $idf;
        if (isset($candidate[$token])) $score += $idf;
    }

    return $total > 0 ? $score / $total : 0.0;
}
