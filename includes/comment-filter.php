<?php
/**
 * Comment filter: decides whether a new comment is published at once or held for a moderator.
 *   ugc_filter_check($name, $comment, $extraTerms, $extraDomains) returns null (publish) or a short rule tag (hold for review):
 *   threat | profanity | custom | link | contact | spam
 * Pure functions with no project dependencies, so it can be tested from the command line. A hit never deletes anything: the
 * comment is held as 'pending' and an admin can approve it (see includes/comment-moderation.php, admin/comments.php).
 * Priority is not holding ordinary comments: word lists only hold clearly abusive or spam wording, and ambiguous words
 * ("casino", "pornography", "sure bet") are left to the admin's own list under Comments > Rules. Wording that is ordinary in some
 * sentences ("you have won the match", "forex trading is risky", "Kenyans must be beaten") is matched by shape, or not at all.
 * Built-in terms are English plus a short Swahili and Luganda list; there is no Luo list yet (add it under Rules).
 */

const UGC_DEFAULT_TERMS = [
    // ── English: profanity. A trailing * also matches longer words ("fuck*" -> fucking, fucker); spelling dodges are listed.
    'fuck*', 'fuk', 'fuking', 'fukin', 'fukker', 'fck*', 'fcuk*', 'fvck*', 'phuck*', 'motherfuck*', 'motherfvck*', 'mutherfuck*', 'mutherfvck*', 'muthafuck*',
    'mothafuck*', 'mthrfck*', 'mofo', 'mofos', 'stfu',
    'shit', 'shits', 'shitty', 'shithead*', 'shithole*', 'shitting', 'bullshit*', 'sht', 'shyt',
    'bitch*', 'btch*', 'biatch*', 'bytch*', 'bastard', 'bastards',
    'ass', 'asshole*', 'arse', 'arsehole*', 'jackass*', 'dumbass*', 'smartass*', 'asswipe*', 'assclown*',
    'dickhead*', 'cocksucker*', 'cunt*', 'cvnt*', 'whore*', 'slut*', 'wanker*', 'twat*', 'piss off',
    'idiot', 'idiots', 'moron', 'morons', 'imbecile*', 'scumbag*', 'douchebag*', 'dirtbag*',
    // ── English: slurs ("niger" the country and "kaffir lime" are not matched, see UGC_SLUR_PATTERNS)
    'nigger*', 'nigga*', 'niqqer*', 'niqqa*', 'faggot*', 'fag', 'fags', 'faggy', 'tranny', 'trannies',
    'paki', 'pakis', 'spic', 'spics', 'wetback*', 'towelhead*', 'raghead*', 'gook', 'gooks',
    'retard', 'retards', 'retarded', 'mongoloid*', 'spastic', 'spastics', 'spaz', 'cripples',
    // ── English: explicit sexual content (policy words such as "pornography" or "sex education" are not held)
    'porn', 'porns', 'porno', 'pornos', 'porn video*', 'porn site*', 'porn pic*', 'porn movie*', 'porn star*', 'free porn', 'watch porn', 'pornhub', 'xvideos', 'xnxx',
    'xxx video*', 'xxx pic*', 'xxx movie*', 'xxx site*', 'sex video*', 'sex tape*', 'sex chat', 'sex hookup*', 'sexual hookup*',
    'blowjob*', 'blow job*', 'handjob*', 'hand job*', 'cumshot*', 'cum on me', 'pussy', 'pussies', 'boobs', 'big tits', 'nice tits', 'show tits', 'show me your tits', 'her tits', 'my tits', 'your tits', 'horny', 'milf', 'milfs', 'dildo*',
    'onlyfans', 'sexting', 'dick pic*', 'big dick', 'suck my dick', 'suck your dick', 'suck a dick', 'suck dick', 'suck my cock', 'suck cock', 'lick your vagina', 'lick my vagina',
    'big black dick', 'black dick', 'huge dick', 'huge cock', 'big cock', 'sex with you', 'have sex with you', 'erotic massage*', 'erotic video*', 'erotic chat*', 'erotic girls', 'erotic pic*', 'masturbat*', 'orgy', 'orgies', 'nudes', 'nude pic*', 'naked girls', 'naked women', 'suck my balls',
    'sexy girls', 'escort girl*', 'call girls in', 'call girl service*', 'call girls available', 'sex with me', 'looking for sex', 'sex tonight', 'your cock', 'my cock', 'big black cock', 'lets have sex', 'let s have sex', 'let us have sex', 'have sex with me', 'wanna have sex',
    // ── Swahili (starter list from a non-native reviewer: please check it with a native speaker)
    'malaya', 'mjinga', 'wajinga', 'mpumbavu', 'wapumbavu', 'pumbavu', 'mshenzi', 'washenzi', 'shenzi', 'mpuuzi', 'wapuuzi', 'mwanaharamu', 'wanaharamu',
    'msenge', 'wasenge', 'hayawani', 'kumbafu',
    'kuma yako', 'kuma mamako', 'wewe ni kuma', 'wewe mbwa', 'mbwa wewe', 'wewe ni mbwa', 'wewe mnyama', 'mnyama wewe', 'wewe fala', 'fala wewe', 'mkundu wako',
    // ── Luganda (same caveat)
    'malaaya', 'omusiru', 'musiru', 'abasiru', 'embwa ggwe', 'ggwe embwa',
    // ── Luo (Acholi, Lango, Dholuo): none built in yet, add them under Comments > Rules.
];

// Commercial / scam phrases (word-boundary matched, same engine). Only wording that has no everyday use is listed here: phrases that
// also turn up in ordinary sentences ("you have won the match", "daily income", "forex trading is risky") are matched by shape in
// UGC_SPAM_PATTERNS, or left to the admin's own list.
const UGC_SPAM_TERMS = [
    'sure tips today', 'sure tips daily', 'sure tips tonight', 'sure bet tips', 'sure odds daily', 'sure odds today', 'sure odds tonight',
    'betting code*', 'booking code*', 'bonus code*', 'promo code*', 'sportybet code*', 'betpawa code*', '1xbet', 'melbet', 'betwinner',
    'forex signal*', 'forex expert*', 'binary option*', 'double your bitcoin',
    'play casino', 'casino bonus',
    'urgent loan*',
    'earn daily from home', 'earn weekly from home', 'daily from home',
    'recover your lost', 'recover lost funds', 'hacker for hire', 'hire a hacker',
    'free iphone', 'claim your prize', 'free robux', 'robux generator', 'free v bucks', 'sub4sub', 'seo services',
    'check my profile', 'check my bio', 'check out my channel', 'subscribe to my channel', 'follow my page', 'follow my channel',
    'free followers', 'buy followers', 'viagra', 'cialis', 'penis enlargement', 'love spell*', 'spell caster*',
    'dm me', 'pm me', 'inbox me', 'hit me up', 'message me privately', 'message me for', 'whatsapp me',
    'contact us on whatsapp', 'join my telegram', 'join our telegram', 'join my whatsapp', 'join our whatsapp',
];

// The phrases dropped from the list above, still held when the letters are spaced out ("f o r e x trading"): spacing is the evasion.
const UGC_SPAM_TERMS_SPACED = [
    'forex trading', 'forex trader*', 'forex broker*', 'bitcoin investment*', 'crypto investment*', 'double your money', 'double your investment',
    'betting tips', 'fixed match*', 'fixed odds', 'instant loan*', 'quick loan*', 'fast loan*', 'loan without*', 'loans without*',
    'online casino', 'casino online', 'free spins', 'earn daily', 'earn weekly', 'ask me how', 'daily income', 'you have won', 'click here',
    'contact me on', 'text me on', 'call me on', 'add me on', 'join telegram', 'join whatsapp', 'sure tips',
];

// Scam shapes (words view). "you have won my heart" or "fixed matches ruin the game" are ordinary, "you have won a car" is not.
const UGC_SPAM_PATTERNS = [
    '/\byou (?:have|ve) (?:just )?won (?:an? |the |our |this )?(?:\w+ )?(?:prize|lottery|jackpot|iphone|car|laptop|cash|gift|voucher|ugx|usd|shillings|dollars|million|free|\d)/u',
    '/\bask me how (?:to|you can|i) (?:earn|make|get|win|become|invest)\b/u',
    '/\b(?:earn|make) (?:ugx |usd )?\d[\d ]*(?: (?:dollars?|usd))? (?:daily|weekly|a day|a week|per day|per week|every day)\b/u',
    '/\bfixed (?:matches|match|odds) (?:tips|today|daily|available|sure|for sale|guaranteed|100|\d)/u',
    '/\b(?:free|best|sure|vip|daily|premium|winning) betting tips\b|\bbetting tips (?:today|daily|tonight|for sale|available|and (?:fixed|sure))\b/u',
    '/\b(?:instant|quick|fast|urgent|easy) loans?(?: \w+){0,3} (?:without|no) (?:collateral|security|guarantor|crb|credit check)\b|\b(?:instant|quick|fast) loans? approv/u',
    '/\bplay (?:online )?casino\b|\bcasino (?:bonus|online)\b|\bonline casino (?:bonus|games|site|offer)\b|\b(?:\d+ )?free spins (?:bonus|no deposit|today|now)\b|\b(?:get|claim|win) (?:\d+ )?free spins\b/u',
    '/\bdouble your (?:money|investment|cash) (?:in|within) (?:\d+|a day|one day|a week|one week) (?:hours|hrs|days?)\b/u',
    // "call me on whatsapp", "text me on 0772": a way to reach the poster off the site; "call me on it" is not
    '/\b(?:call|text|message|contact|whatsapp|add|reach|dm) (?:me|us) (?:on|via|at|through) (?:my |our )?(?:whatsapp|telegram|snap(?:chat)?|insta(?:gram)?|facebook|fb|tiktok|signal|imo|number|phone|mobile|email|e mail|gmail|\d)/u',
    '/\bclick here (?:to|for|and) (?:win|claim|earn|join|get|watch|see|download|register|buy|visit|chat|follow|subscribe|receive)\b/u',
    // forex and crypto pitches: "forex trading signals", "I made 5000 dollars with forex last week"; news about forex scams has none of this wording
    '/\bforex trading (?:signals?|course|classes|training|mentor(?:ship)?|made easy|account)\b|\b(?:crypto|bitcoin) investment (?:plans?|opportunity|platform|with \d)/u',
    '/\b(?:forex|crypto|bitcoin)(?: \w+){0,8} (?:i|we) (?:made|earned|make|earn|withdrew)\b|\b(?:i|we) (?:made|earned|make|earn|withdrew)(?: \w+){0,8} (?:from|with|using|through) (?:forex|crypto|bitcoin)\b/u',
    '/\bbetting tips (?:and |with )?(?:sure|fixed|vip) /u',
    // quick-money pitches: "make money fast online", "get rich quick with ...", "make money from home, dm me"; "a get rich quick scheme" is ordinary talk
    '/\b(?:make|earn) (?:money|cash)(?: \w+)? (?:fast|quickly) (?:online|from home|today|now|with|using|dm|inbox|message|contact|call|text|click|link|whatsapp|\d)/u',
    '/\b(?:get|become|getting) rich (?:quick|quickly|fast) (?:with|online|today|now|dm|inbox|message|contact|click|join|\d)/u',
    '/\b(?:(?:dm|pm|inbox|message|contact|whatsapp|text|call) (?:me|us)|join (?:my|our)|click (?:the |this )?link|link in bio)\b(?: \w+){0,10} (?:make|earn|making|earning) (?:\w+ ){1,3}(?:from|at) home\b|\b(?:make|earn|making|earning) (?:\w+ ){1,3}(?:from|at) home\b(?: \w+){0,10} (?:(?:dm|pm|inbox|message|contact|whatsapp|text|call) (?:me|us)|join (?:my|our)|click (?:the |this )?link|link in bio)\b/u',
    '/\b(?:send|drop|leave) me an? (?:message|text|dm|pm|mail) (?:for|to get|on whatsapp|privately)\b/u',
    '/\b(?:win|claim|get) an? (?:brand new|free) (?:car|iphone|laptop|tv|phone|samsung|motorcycle|bike)\b/u',
    '/\bhack (?:any|your|his|her|someone s|a|my) (?:facebook|whatsapp|instagram|phone|account|gmail|email|tiktok)\b|\bhire (?:an? )?(?:\w+ ){0,2}hackers?\b/u',
    '/\bguaranteed \d+ ?x\b|\b\d+ ?x returns\b|\b\d+ ?% (?:guaranteed|sure) (?:profit|win|return|returns)\b/u',
    '/\b(?:recovered|recover|recovering) (?:all )?(?:my|your|the) (?:lost|stolen|scammed|hacked)(?: \w+)? (?:funds|money|bitcoin|btc|crypto|usdt|ethereum|accounts?|investments?|coins|wallet)\b/u',
    '/\bcheap (?:\w+ )?(?:followers|likes|views|subscribers)\b|\b(?:buy|order|cheap|cheapest) (?:\w+ )?pills\b|\bpills online\b/u',
    '/\b(?:send|give|share) me your (?:bank|account|card|pin|password|otp)\b/u',
];

// Fragments shared by the threat patterns. Words view: lower case, apostrophes are spaces ("i ll kill you").
const UGC_F_WILL = '(?:(?:i|we) (?:will|wil+|ll|shall|am going to|m going to|are going to|re going to|going to|gonna|m gonna|wanna|want to)|im (?:going to|gonna|goin to)|ill|ima|imma)';
const UGC_F_HARD = '(?:kil+|murder|stab|rape|lynch|behead|slaughter|strangle|torture|assassinate|poison|suffocate|electrocute)';
const UGC_F_SOFT = '(?:shoot|hang|burn|stone|drown)';
// What may follow the target without it being a threat: "hang her portrait", "shoot you a message", "shoot your wedding video", "burn them to a CD",
// "hang him out to dry", "kill them with kindness". "s" = possessive: "hang the president s portrait".
const UGC_F_OK = '(?! (?:s|a|an|the|some|my|over|off|up|out|portrait|picture|photo|poster|painting|frame|coat|hat|cd|dvd|copy|message|text|mail|email|link|file|invite|ticket|number|ball|wedding|video|film|movie|documentary|scene|birthday|party|ceremony|graduation|softly|on the wall|in close|in the studio|in studio|with (?:kindness|love|laughter|a smile)|from (?:a |the |above|below|behind|another|different)|with (?:a |the |two |three )?cameras?|(?:all )?(?:to|onto|on) (?:a |the |my )?(?:cd|dvd|disc|disk|usb|flash))\b)';
const UGC_F_OK_HARD = '(?! (?:softly|with (?:kindness|love|laughter|a smile))\b)'; // the same for plain killing words, where "kill you a ..." is not an idiom
const UGC_F_BODY = '(?:your|ur|his|her|their) (?:throat|neck|head|legs?|arms?|face|skull|fingers?|hands?|ribs|jaw|nose|teeth|eyes|body|knees|chest)\b';
const UGC_F_GROUPS = '(?:gays?|homosexuals?|lesbians?|queers?|lgbtq?(?: people)?|gay people|muslims?|christians?|jews?|albinos?|refugees|foreigners|immigrants|arabs|blacks|whites|europeans|nigerians|ethiopians|burundians|rwandans|congolese|somalis|kenyans|tanzanians|sudanese|indians|asians|chinese|baganda|banyankole|bakiga|basoga|acholi|langi|iteso|lugbara|alur|karamojong|banyoro|batoro|bagisu|northerners|southerners|westerners|easterners)';
// Office-holders and public figures: "hang the president" has no pronoun, but is just as much incitement. Referees, coaches and the (loud)speaker are not listed.
const UGC_F_PUBLIC = '(?:(?:the |that |this |our |your |these |those |all )?(?:\w+ )?(?:president|prime minister|ministers?|mps|politicians?|journalists?|reporters?|editors?|anchors?|newsreaders?|judges?|police|policemen|soldiers|presenters?|rwot|kabaka|speaker of parliament|parliament speaker)|speaker (?:among|kadaga|oulanyah)|museveni|bobi wine|kyagulanyi|besigye|muhoozi|kagame)';
// Someone pointed at without a pronoun or a group name: "kill this man", "they should hang that guy"
const UGC_F_PERSON = '(?:(?:this|that|these|those|the) (?:\w+ )?(?:man|men|woman|women|guy|guys|boy|girl|person|people))';
const UGC_F_TARGET = '(?:him|her|them|you|u|ya|everyone|everybody|all of (?:them|you)|' . UGC_F_PERSON . '|(?:all (?:the )?|the |those |these )?' . UGC_F_GROUPS . '|' . UGC_F_PUBLIC . ')';
const UGC_F_SUBJ = '(?:you|u|he|she|they|' . UGC_F_PERSON . '|all of (?:them|you))'; // "thieves should be hanged" is ordinary anger, so group nouns are not here
// Buildings, offices and property: "burn down the station", "bomb the radio station", "I will burn your shop". "the house" is left out ("burn the house down" is a party phrase).
const UGC_F_BUILDING = '(?:(?:your|ur|his|her|their) (?:\w+ )?(?:houses?|homes?|shops?|stations?|studios?|offices?|building|buildings|church|churches|mosques?|schools?|markets?|tv|radio|cars?|business|businesses|village|compound|stalls?)|(?:the|this|that|these|those|oroma) (?:\w+ )?(?:stations?|studios?|offices?|parliament|building|buildings|church|churches|mosques?|schools?|markets?|tv|radio)|oroma tv|oroma|parliament)\b';
const UGC_F_ARSON = '(?:burn|torch|bomb|blow up|set fire to|petrol bomb)(?: down)?';
const UGC_F_ANIMALS = '(?:animals|vermin|pigs|dogs|rats|cockroaches|snakes|monkeys|baboons|savages|filth|parasites|apes)';
// Words that can sit between "are" and an animal or crime word: "the Bakiga are filthy dogs". "not" and "people" are not here, so "Muslims are not terrorists" is fine.
const UGC_F_ADJ_HATE = 'filthy|dirty|stupid|dumb|useless|lazy|evil|wicked|nasty|disgusting|sick|worthless|violent|backward|primitive|ugly|bloody|stinking|damn|godless|cursed|dangerous|mad|crazy|savage|wild|mindless';
// Where "die" is meant as a joke or a farewell: "you will die laughing", "I hope he dies peacefully", "we all have to die one day".
const UGC_F_DIE_OK = '(?! (?:laughing|laughter|happy|peacefully|in peace|rich|trying|with dignity|one day|eventually|someday|from laughing|of (?:laughter|laughing|old age|shock|embarrassment|boredom|hunger|joy)))';

// Threats and incitement (words view). A hit is only a hold, so "kill them" in a football chant costs a moderator one click,
// but plain football talk ("we will beat them", "we are coming for you this weekend", "Kenyans must be beaten"), news retellings
// ("he tried to kill her", "residents beat him to death") and everyday phrases are left alone.
const UGC_THREAT_PATTERNS = [
    // "I will kill you", "ill kil you", "im gonna shoot u", "we shall stab him"; "i will not kill you" is not a threat
    '/\b' . UGC_F_WILL . ' (?:(?!not |never |n t )\w+ )?' . UGC_F_HARD . ' (?:you|u|ya|him|her|your|ur)\b' . UGC_F_OK_HARD . '/u',
    '/\b' . UGC_F_WILL . ' (?:(?!not |never |n t )\w+ )?' . UGC_F_SOFT . ' (?:(?:you|u|ya|him|her)\b|' . UGC_F_BODY . ')' . UGC_F_OK . '/u',
    '/\b' . UGC_F_WILL . ' (?:(?!not |never |n t )\w+ )?(?:stab|rape|lynch|behead|strangle|torture|shoot) (?:them|everyone|everybody|all of (?:you|them))\b/u',
    '/\b' . UGC_F_WILL . ' (?:(?!not |never |n t )\w+ )?' . UGC_F_HARD . ' ' . UGC_F_PUBLIC . '\b' . UGC_F_OK . '/u',
    '/\b' . UGC_F_WILL . ' (?:\w+ )?end (?:you|u|(?:your|ur) (?:life|existence))\b/u',
    '/\b' . UGC_F_WILL . ' (?:\w+ )?(?:find|know) where (?:you|u) (?:live|stay|sleep|work)(?: and| then)?\b/u',
    '/\b' . UGC_F_WILL . ' (?:\w+ )?(?:kil+|murder|slaughter) (?:all of you|everyone|everybody)\b/u',
    '/\b(?:burn|hang) (?:them|all of them|all of you) (?:all )?(?:alive|in their homes?|in their houses?|down|to death|at the stake)\b/u',
    '/\b' . UGC_F_WILL . ' (?:\w+ )?(?:find|get|catch|hunt|track) (?:you|u|him|her|them)(?: down)? and (?:kil+|finish|end|hurt|rape|stab|shoot|beat|burn|cut|slit|strangle|choke|murder|slaughter|behead)\b/u',
    '/\b' . UGC_F_WILL . ' (?:\w+ )?(?:hunt|track) (?:you|u) down\b/u',
    // "I will hurt you", "I will beat you up", "I will cut your head off", "I will break your legs"
    '/\b' . UGC_F_WILL . ' (?:(?!not |never |n t )\w+ )?(?:hurt (?:you|u|ya|him|her)|beat (?:you|u|ya|him|her) up)\b/u',
    // "I will cut you", but not "cut you some slack", "cut you off", "cut you a cheque"
    '/\b' . UGC_F_WILL . ' (?:(?!not |never |n t )\w+ )?cut (?:you|u|ya|him|her)\b(?! (?:some|a|an|off|out|in|up|loose|slack|the|down|short|from|another|my|your|his|her|their|deal|check|cheque|break)\b)/u',
    '/\b' . UGC_F_WILL . ' (?:(?!not |never |n t )\w+ )?(?:slit|cut|break|smash|crack|slash|chop|twist|rip|tear|punch|kick|slap|stab|shoot|crush|bash|bust|blow) (?:off |out )?' . UGC_F_BODY . '/u',
    // "I will beat the hell out of you", "I will make you suffer", "I will put a bullet in your head"
    '/\b' . UGC_F_WILL . ' (?:(?!not |never |n t )\w+ )?(?:beat|kick|knock) the (?:hell|shit|crap|life|daylights) out of (?:you|u|ya|him|her|them)\b/u',
    '/\b' . UGC_F_WILL . ' (?:(?!not |never |n t )\w+ )?beat (?:you|u|ya|him|her|them) (?:until|till) (?:you|u|he|she|they) (?:die|are dead|bleed|cry|beg)\b/u',
    '/\b' . UGC_F_WILL . ' (?:(?!not |never |n t )\w+ )?make (?:you|u|him|her) (?:suffer|bleed|disappear)\b(?! through\b| with\b| from (?:the |my |this )?(?:group|chat|list|page|channel|screen|app)\b)/u',
    // "I will slap you and break your teeth"
    '/\b' . UGC_F_WILL . ' (?:\w+ ){1,4}and (?:slit|cut|break|smash|crack|slash|chop|rip|tear|punch|kick|crush) (?:off |out )?' . UGC_F_BODY . '/u',
    '/\b' . UGC_F_WILL . ' (?:(?!not |never |n t )\w+ )?(?:put|send|plant) a bullet (?:in|through|into) (?:your|ur|his|her|their|the) (?:head|chest|back|skull|brain|face|heart)\b/u',
    // "you will be killed" (a warning that is a threat); "he will be killed" is news talk
    '/\b(?:you|u)(?: and (?:your|ur) \w+)? (?:will|ll|shall|gonna|are going to|re going to) (?:all )?be (?:killed|shot|raped|hanged|lynched|stabbed|beheaded|slaughtered|burnt|burned)\b(?! (?:if|when|unless)\b)/u',
    // "beat him to death" is a threat after "will" or "should", not in "residents beat him to death" (a news retelling)
    '/\b(?:will|ll|shall|gonna|going to|should|must|lets|let us|let s) beat (?:you|u|him|her|them) to death\b/u',
    '/\b(?:coming|come) for (?:you|u) (?:at night|in your sleep)\b/u',
    '/\b(?:coming|come) for (?:you|u)(?: and (?:your|ur) \w+)?(?: (?:tonight|tomorrow|today|at night|in your sleep))? (?:and|to) (?:\w+ ){0,3}(?:kil+|finish|end|hurt|burn|rape|stab|shoot|murder)\b/u',
    '/\b(?:pour|throw|splash|spray) acid (?:on|at|over|in) (?:you|u|him|her|them|your|his|their|ur)\b/u',
    '/\b' . UGC_F_WILL . ' (?:(?!not |never |n t )\w+ )?make sure (?:you|u|he|she|they|your \w+|his \w+|her \w+) (?:die|dies|are dead|get killed|suffer)\b/u',
    '/\b(?:you|u)(?: and (?:your|ur) \w+)? (?:will|ll|shall|gonna|are going to|re going to) (?:\w+ )?pay (?:for (?:this|it|that) )?with (?:your|ur) (?:lives|life|blood|head)\b/u',
    '/\b(?:expect|get|gets|getting|deserve|deserves|receive|eat) (?:a |one |some )?bullets? (?:(?:in|to|through|between) (?:your|ur|his|her|their|the) (?:head|chest|back|skull|brain|face|heart|eyes)|for (?:you|u|him|her))\b/u',
    // buildings and property: "I will burn down Oroma TV", "we will bomb the station", "or we burn your shops"
    '/\b' . UGC_F_WILL . ' (?:(?!not |never |n t )\w+ )?' . UGC_F_ARSON . ' ' . UGC_F_BUILDING . '/u',
    '/\b' . UGC_F_WILL . ' (?:\w+ ){1,5}and ' . UGC_F_ARSON . ' (?:it down|them down|' . UGC_F_BUILDING . ')/u',
    '/\b(?:(?:i|we) (?:will|wil+|ll|shall|gonna|am|m|are|re)|im|ill) (?:coming|come|going|go) (?:(?:[a-z]+ ){1,5}?)?to ' . UGC_F_HARD . ' (?:you|u|ya|him|her|them|everyone|all of you)\b' . UGC_F_OK_HARD . '/u',
    '/\b(?:or|otherwise|else|then) (?:i|we|they) (?:will |shall |ll |gonna )?(?:burn|torch|bomb|loot|kill|stab|rape|lynch) (?:you|u|ya|your|ur|their|his|her)\b' . UGC_F_OK . '/u',
    // third person and imperative: "someone should rape her", "they should hang them all"
    '/\b(?:someone|somebody|they|we|people|you|u|police|army|soldiers|government|let us|lets|let s) (?:should|must|ought to|need to|needs to|have to|has to|better) (?:(?:just|all|really|please|now) )?(?:' . UGC_F_HARD . '|' . UGC_F_SOFT . ') ' . UGC_F_TARGET . '\b' . UGC_F_OK . '/u',
    '/\b(?:someone|somebody|they|we|people|you|u|police|let us|lets|let s) (?:should|must|ought to|need to|needs to|have to|has to|better) (?:just |all |really )?(?:slit|cut|break|smash|crack|slash|chop|punch|kick|slap) (?:off |out )?' . UGC_F_BODY . '/u',
    // "she deserves to be raped", "you need to be killed", "he must be killed"
    '/\b' . UGC_F_SUBJ . ' (?:should|must|ought to|needs? to|deserves? to|has to|have to)(?: all)? (?:to )?(?:be )?(?:killed|raped|hanged|hung(?! out| up| on| over| around)|lynched|stoned|burnt(?! out)|burned(?! out)|slaughtered|executed|castrated|beheaded|assassinated|stabbed|shot(?! (?:at|in a|in the|on|by|with)\b))\b/u',
    '/\b' . UGC_F_SUBJ . ' (?:should|must|ought to|needs? to|deserves? to|has to|have to)(?: all)? die\b' . UGC_F_DIE_OK . '/u',
    '/\bdeath to (?:the |all |every |these |those |that )?(?:\w+ )?(?:president|prime minister|museveni|bobi wine|opposition|police|army|soldiers|king|rwot|kabaka|journalists?|reporters?|presenters?|media|oroma|nrm|nup|judges?|ministers?|him|her|them|you|u|' . UGC_F_GROUPS . ')\b/u',
    // "you will die tonight", "you are a dead man", "i hope you die"
    '/\b(?:you|u) (?:will|ll|shall|are going to|re going to|gonna) die (?:tonight|tomorrow|today|soon|slowly|painfully|like|next|before)\b(?! if\b| when\b| unless\b)/u',
    '/\b(?:you|u) (?:are|re|r) (?:a )?dead (?:man|woman|meat)\b/u',
    '/\b(?:(?:i|we) )?(?:hope|wish|pray) (?:that )?(?:you|u|he|she|they|(?:your|ur|his|her|their) (?:(?:whole|entire|dirty|stupid) )?(?:family|families|mothers?|fathers?|mum|mom|dad|parents|children|kids|sisters?|brothers?|wife|husband|sons?|daughters?|relatives|clan|tribe|people))(?: all)? (?:die|dies|would die|will die|get killed|get raped|get shot|are killed|rot|burn)\b(?! (?:laughing|laughter|happy|peacefully|in peace|old|of old age|rich|quickly|painlessly|well|a happy))/u',
    '/\bdie in (?:a |the )?(?:fire|hell|pain)\b/u',
    '/\bdrop dead\b(?! (?:gorgeous|beautiful|handsome|sexy|fit|cute|stunning|simple|easy|serious|cheap|funny|boring|obvious|good|great|fast))/u',
    // "i will send boys to kill you", "someone should burn that mosque"
    '/\b' . UGC_F_WILL . ' (?:\w+ )?(?:send|hire|pay) (?:\w+ ){0,3}to (?:kil+|beat|rape|stab|shoot|attack|hurt) (?:you|u|him|her)\b/u',
    '/\bburn (?:down )?(?:that|this|the|those|these|their) (?:mosques?|churches|church|temples?|synagogues?)\b/u',
    '/\b(?:kill|hang|shoot|burn|stab) (?:yourself|urself|your self)\b/u',
    '/\bkys\b/u',
    '/\bgo (?:and )?die\b' . UGC_F_DIE_OK . '/u',
    '/\b(?:i|we) know where (?:you|u) (?:live|stay|sleep)\b/u',
    // Swahili: "I/we will kill you"
    '/\b(?:nitakuua|tutakuua|nitawaua|tutawaua|nitakuchinja|tutakuchinja|nitakuchoma)\b/u',
    // Luganda: "nja kukutta" (I will kill you)
    '/\bnja kukutta\b/u',
    // spaced-out letters are joined into one word by ugc_squash: "i w i l l k i l l y o u", "k i l l u"
    '/ (?:(?:i|we)(?:will|wil+|ll|shall|gonna|amgoingto|wanna))?(?:kil+|murder|stab|rape|shoot|lynch|behead|slaughter)(?:you|u|him|her) /u',
    // hate aimed at a group: "gays should be burnt alive", "lgbt people are sick and should be killed"; sports talk ("Kenyans must be beaten") is not here
    '/\b' . UGC_F_GROUPS . ' (?:(?:are|r|is) (?!not\b|never\b)(?:\w+ ){1,3}(?:and|so) )?(?:should|must|ought to|need to|deserve to|have to)(?: all)? (?:be )?(?:killed|burnt|burned|hanged|hung(?! out| up| on)|shot|stoned|lynched|executed|slaughtered|exterminated|raped|castrated|die|burn)\b(?! laughing| laughter)/u',
    '/\b' . UGC_F_GROUPS . ' (?:(?:are|r|is) (?!not\b|never\b)(?:\w+ ){1,3}(?:and|so) )?deserve death\b/u',
    '/\b(?:ugandans|africans) (?:should|must|need to|deserve to)(?: all)? (?:be )?(?:killed|burnt|burned|hanged|stoned|lynched|exterminated|raped|castrated)\b/u',
    '/\ba good (?:muslim|christian|jew|gay|lesbian|homosexual|albino|refugee|foreigner|immigrant) is a dead (?:muslim|christian|jew|gay|lesbian|homosexual|albino|refugee|foreigner|immigrant)\b/u',
    '/\bsend (?:all |the |those |these )?(?:\w+ )?' . UGC_F_GROUPS . ' back to hell\b/u',
    // the same for office-holders, without "rot" ("corrupt ministers should rot in jail" is ordinary anger)
    '/\b' . UGC_F_PUBLIC . ' (?:should|must|ought to|needs? to|deserves? to|has to|have to) (?:all )?(?:be )?(?:killed|hanged|hung(?! out| up| on| over| around)|shot(?! (?:at|in a|in the|on|by|with)\b)|stoned|lynched|executed|beheaded|assassinated|burnt|burned|slaughtered|die\b' . UGC_F_DIE_OK . ')\b/u',
];

// Imperatives need the start of a sentence ("Kill him now."), so they run on the sentence view: " | kill him now | ".
const UGC_THREAT_SENTENCE_PATTERNS = [
    '/ \| (?:(?:now|just|please|pls|and|then|go|go and|lets|let us|let s|someone|somebody|anyone|anybody)(?: please| pls| just)? )?(?:' . UGC_F_HARD . '|' . UGC_F_SOFT . '|exterminate|necklace) ' . UGC_F_TARGET . '\b' . UGC_F_OK . '/u',
    // mob justice: "lynch the thief", "stone that thief to death"
    '/ \| (?:(?:now|just|please|pls|and|then|go|go and|lets|let us|let s|someone|somebody)(?: please| pls| just)? )?(?:lynch|necklace|stone|burn)(?: alive)? (?:the|that|this|these|those) (?:\w+ )?(?:thief|thieves|suspect|suspects|witch|witches|rapist|rapists|culprit|culprits)\b(?! (?:s )?(?:portrait|picture|photo))/u',
    // "burn the radio station", "bomb the parliament", "we should burn down the station"
    '/ \| (?:(?:now|just|please|pls|and|then|go|go and|lets|let us|let s|someone|somebody|we should|we must|we need to|they should|you should) )?' . UGC_F_ARSON . ' ' . UGC_F_BUILDING . '/u',
    '/ \| (?:beat|kill|hang) (?:you|u|him|her|them) to death\b/u',
    '/ \| (?:(?:now|just|please|pls|and|then|go|lets|let us|let s|someone|somebody) )?(?:slit|cut|break|smash|crack|slash|chop|punch|kick|slap) (?:off |out )?' . UGC_F_BODY . '/u',
    // "you will die." at the end of a sentence (with a time word it is in the words patterns)
    '/ (?:you|u|you all|all of you)(?: all)? (?:will|ll|shall|are going to|re going to|gonna)(?: all)? die \| /u',
];

// Dehumanising or hateful wording and slurs with an exception (words view). Tag: profanity.
const UGC_SLUR_PATTERNS = [
    '/\bkaffirs?\b(?! lime| leaf| leaves)/u',
    '/\b(?:all |those |these |the )?' . UGC_F_GROUPS . ' (?:are|r|is) (?:all |just |nothing but |such )?(?:(?:' . UGC_F_ADJ_HATE . '|\w+ and) ){0,2}(?:' . UGC_F_ANIMALS . '|devils|terrorists|rapists|murderers|thieves|criminals|inferior|subhumans?|scum|diseased)\b/u',
    '/\b(?:all )?' . UGC_F_GROUPS . '(?: \w+){0,3} (?:they|these people|those people) (?:are|r) (?:all |just )?(?:(?:' . UGC_F_ADJ_HATE . ') )?(?:' . UGC_F_ANIMALS . '|devils|terrorists|rapists|murderers|scum|subhumans?)\b/u',
    // Ugandans and Africans: only the animal, devil and "inferior" wording ("Ugandans are thieves" is ordinary self-criticism)
    '/\b(?:all |those |these |the )?(?:ugandans|africans) (?:are|r|is) (?:all |just |nothing but |such )?(?:(?:' . UGC_F_ADJ_HATE . ') ){0,2}(?:' . UGC_F_ANIMALS . '|devils|inferior|subhumans?)\b/u',
    '/\b(?:those|these|that|the) (?:\w+ ){0,3}(?:people|tribe|tribes|lot|race|folks)(?: \w+){0,4} (?:are|is|r) (?:all |just |nothing but )?(?:\w+ and )?' . UGC_F_ANIMALS . '\b/u',
    // "you dirty jew", "stinking muslims"
    '/\b(?:dirty|filthy|stinking|stupid|nasty|disgusting|dumb) (?:jews?|muslims?|christians?|gays?|lesbians?|homosexuals?|albinos?|refugees?|foreigners?|immigrants?)\b/u',
    '/\bgo back to (?:your|ur) (?:country|village|land|continent|tribe)\b/u',
    // nude pictures offered or asked for ("send nude photos", "hot naked girls", "see my naked body"); news about "leaked naked photos" is not held
    '/\b(?:send|sending|sell|selling|share|sharing|buy|free|hot|sexy|my|your|her|more)(?: \w+){0,2} (?:nudes|(?:nude|naked) (?:pics?|photos?|pictures?|videos?|images?|girls|women))\b/u',
    '/\b(?:see|view|watch|look at|check out|show(?: me)?) (?:my|your|her) (?:naked|nude)\b/u',
    '/(?<!leaked )(?<!shared )(?<!the )(?<!those )\b(?:nude|naked) (?:pics?|photos?|pictures?|videos?) of (?:her|your|my)\b/u',
    '/\bwomen (?:(?!(?:do|does|did|should|must|never|not|don|doesn|isn|aren)\b)\w+ ){0,3}belong (?:in|at) (?:the )?(?:kitchen|home)\b/u',
    '/\b(?:women|girls) (?:are|r) (?:all |just |so )?(?:stupid|dumb|useless|inferior|animals|property|sluts|whores|objects|trash|garbage)\b/u',
];

// Directed insults. They run on the clause view (ugc_clauses), so "Thank you. Stupid decision by the coach" is two clauses, not an insult.
// "you are such a stupid fool", "youre a bloody idiot"; third person ("stupid policy") is left alone, and so are animal names and "dick"
// ("you are a dog lover", "thank you Dick").
const UGC_F_INSULT_ADJ = 'dumb|stupid|silly|ugly|useless|filthy|lazy|worthless|shameless|foolish|total|complete|real|big|bloody|damn|damned|stinking|pathetic|disgusting|nasty|rotten|miserable|dirty|sick|mad|crazy|brainless|hopeless|fat|smelly|stinky';
const UGC_F_INSULT_ADJ_STRONG = 'dumb|stupid|ugly|useless|filthy|lazy|worthless|shameless|foolish|bloody|damn|damned|stinking|pathetic|disgusting|nasty|rotten|miserable|dirty|brainless|hopeless|fat|smelly|stinky'; // no "big", "real", "total": "you big dog"
const UGC_INSULT_PATTERNS = [
    '/\b(?:(?:you|u) (?:are|re|r)|youre|ur (?:an?|so|such))(?: (?:an?|such|so|really|just|nothing but|the|' . UGC_F_INSULT_ADJ . ')){0,3} (?:idiots?|stupid|stupids|morons?|fools?|imbeciles?|dumb|clowns?|scum|scumbags?|worthless|useless|pricks?|cunts?|bastards?)\b(?! (?:prince|princess|show|school|car|fish)\b)/u',
    // "you piece of crap", "he is a useless piece of garbage" (a person, not a film or a phone)
    '/\b(?:(?:you|u|he|she) (?:(?:are|re|r|is) )?|youre |ur (?=an? ))(?:(?:an?|such|stupid|useless|worthless|dumb|total|complete|real|filthy|dirty|lazy|ugly|damn|bloody|little|pathetic|disgusting) )*pieces? of (?:shit|crap|garbage|trash|junk|filth|dirt)\b/u',
];

// Links to these (and their sub-domains) are fine; admin adds more in the "comment_allowed_domains" setting.
// Social sites and well-known news sources people quote; the station's own Telegram channel is a path rule (t.me is a chat-invite host).
const UGC_ALLOWED_DOMAINS = [
    'oromatv.com', 'youtube.com', 'youtu.be', 'facebook.com', 'fb.com', 'fb.watch', 'x.com', 'twitter.com', 'instagram.com', 'tiktok.com', 'wikipedia.org',
    'bbc.com', 'bbc.co.uk', 'reuters.com', 'aljazeera.com', 'apnews.com', 'monitor.co.ug', 'nilepost.co.ug', 'newvision.co.ug', 'independent.co.ug',
    'observer.ug', 'chimpreports.com', 'theeastafrican.co.ke', 'nation.africa',
];
const UGC_ALLOWED_LINKS = ['t.me/oromatv'];

// Link shorteners and chat-invite hosts: a link on its own, with or without a path.
const UGC_SHORTENERS = 'bit\.ly|tinyurl\.com|goo\.gl|t\.me|wa\.me|is\.gd|cutt\.ly|rb\.gy|shorturl\.at|t\.co|ow\.ly|tiny\.cc|buff\.ly|lnkd\.in|rebrand\.ly|v\.gd|bl\.ink|adf\.ly|bit\.do|soo\.gd|t\.ly|linktr\.ee|wa\.link|forms\.gle|discord\.gg|chat\.whatsapp\.com|telegram\.me';
// A bare "name.tld" is a link when the TLD is typed in lower case ("scamsite.com") or in all capitals ("SCAMSITE.COM", 3+ letters), or is
// a capitalised or mixed-case .Com/.Net/.Org-style one ("Evil.Com"). A capitalised short or word-like TLD is a missing space after a full
// stop ("Thank you.Ug", "Well done.Online", "beautiful.Club") and is a link only with a path, a letter plus a digit or hyphen in the name,
// three or more labels, or a cue word before it. TLDs that are also everyday words need one of those signs in lower case too.
const UGC_TLDS_FIRM = 'com|net|org|info|biz|xyz|ug|ke|tz|rw|za|uk|ng|gh|io|cc|ws|co|tk|ml|ga|cf|gq|ru|cn|ly|gl|gd|vc|pw|su|mobi|icu';
// Words right before a bare "name.tld" that say it is meant as a link ("visit scam.club"). Whole words only: "great show.live" is not "at show.live".
const UGC_CUE_NARROW = '~(?i:\b(?:visit|go to|goto|check out|checkout|click|browse|join|register at|sign up at|log ?in to|download from))\s+$~';
const UGC_CUE_WIDE = '~(?i:\b(?:visit|go to|goto|check out|checkout|open|join|click|browse|try|register at|sign up at|log ?in to|download from|see|watch|read|at|on|via|from|use))\s+$~';
const UGC_TLDS_FIRM3 ='com|net|org|info|biz|xyz|mobi|icu'; // the firm TLDs of 3+ letters: a link in any letter case
const UGC_TLDS_WORD ='top|club|online|site|shop|live|link|app|store|click|bet|cash|win|vip|me|tv|pro|work|today|world|space|fun|buzz|date|party|review|stream|download|trade|loan|money|life|news|games|casino|poker|bingo|sex|xxx|porn|adult|tips|tools|help|free|best|cheap|gold|sale|show|social|team|vote|wiki';

const UGC_DIACRITICS = [
    'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','ā'=>'a','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ē'=>'e','ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','ī'=>'i',
    'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o','ō'=>'o','ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ū'=>'u','ý'=>'y','ÿ'=>'y','ç'=>'c','ñ'=>'n','ß'=>'ss',
    'ı'=>'i','ł'=>'l','đ'=>'d','æ'=>'ae','œ'=>'oe','ƒ'=>'f',
];

// Cyrillic and Greek letters that look like Latin ones ("fuсk" with a Cyrillic с).
const UGC_HOMOGLYPHS = [
    'а'=>'a','е'=>'e','ё'=>'e','і'=>'i','ї'=>'i','ј'=>'j','к'=>'k','м'=>'m','н'=>'h','о'=>'o','р'=>'p','с'=>'c','т'=>'t','у'=>'y','х'=>'x',
    'ѕ'=>'s','һ'=>'h','ԁ'=>'d','ԛ'=>'q','ԝ'=>'w','ѵ'=>'v','ӏ'=>'l','ө'=>'o','ү'=>'y','ɡ'=>'g',
    'α'=>'a','ε'=>'e','ι'=>'i','κ'=>'k','ν'=>'v','ο'=>'o','ρ'=>'p','τ'=>'t','υ'=>'u','χ'=>'x','ϲ'=>'c',
];

/** Lower-case, drop invisible characters, fold full-width/styled letters, strip accents and map look-alike letters ("FÚCK", "ｆｕｃｋ", "fuсk" -> "fuck"). */
function ugc_norm(string $s): string
{
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}\x{FEFF}\x{00AD}]/u', '', $s) ?? $s;
    if (class_exists('Normalizer')) {
        $d = Normalizer::normalize($s, Normalizer::FORM_KD);
        if ($d !== false) {
            $s = $d;
        }
    } else {
        // no intl on this server: at least fold full-width letters by hand
        $s = preg_replace_callback('/[\x{FF01}-\x{FF5E}]/u', static fn(array $m): string => mb_chr(mb_ord($m[0], 'UTF-8') - 0xFEE0, 'UTF-8'), $s) ?? $s;
    }
    // accents and combining marks go; styled capitals (math bold, small capitals) decompose to upper case, so lower-case again
    $s = mb_strtolower(preg_replace('/\p{Mn}+/u', '', $s) ?? $s, 'UTF-8');
    $s = strtr($s, UGC_DIACRITICS);
    $s = strtr($s, UGC_HOMOGLYPHS);
    return strtr($s, ["\u{2019}" => "'", "\u{2018}" => "'", "\u{201C}" => '"', "\u{201D}" => '"']);
}

/** Words view: everything that is not a letter or digit becomes one space, padded: " i ll kill you ". */
function ugc_words(string $n): string
{
    return ' ' . trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $n) ?? $n) . ' ';
}

/** Sentence view: like the words view but sentence breaks stay as " | ", so imperatives can be told from mid-sentence verbs. */
function ugc_sentences(string $n): string
{
    $s = preg_replace('/[.,!?;:()\n\r"]+/u', ' | ', $n) ?? $n;
    return ' | ' . trim(preg_replace('/[^\p{L}\p{N}|]+/u', ' ', $s) ?? $s) . ' | ';
}

/** Clause view: like the sentence view but a comma does not break it, so "you, idiot" stays together and "Thank you. Stupid decision" does not. */
function ugc_clauses(string $n): string
{
    $s = preg_replace('/[.!?;:()\n\r"]+/u', ' | ', $n) ?? $n;
    return ' | ' . trim(preg_replace('/[^\p{L}\p{N}|]+/u', ' ', $s) ?? $s) . ' | ';
}

/**
 * "you idiot", "you stupid fool", "shut up you moron": an insult word straight after "you". Not when "you" is the object of a verb
 * ("thank you fools", "I told you idiots"), and a bare adjective ("you stupid") only counts at the end of its clause.
 * "clown" and animal names only count after an insulting adjective ("you ugly clown", "you stupid pig"): "you clown" is as often banter
 * as abuse, and "you are a clown" is in UGC_INSULT_PATTERNS.
 */
function ugc_vocative_insult(string $clauses): bool
{
    $adj = '(?: (?:' . UGC_F_INSULT_ADJ . '))';
    $adjS = '(?: (?:' . UGC_F_INSULT_ADJ_STRONG . '))';
    $animals = 'cows?|pigs?|dogs?|goats?|donkeys?|monkeys?|rats?|snakes?|animals?|beasts?';
    $re = '/\b(?:you|u)(?:' . $adj . '{0,2} (?:idiots?|morons?|fools?|imbeciles?|scum|scumbags?|pricks?|cunts?|bastards?|dumbass(?:es)?)\b'
        . '|(?: (?:are|re|r)(?: (?:an?|such|so)){0,2})?(?:' . $adj . '{1,2} (?:clowns?|devils?|liars?|thieves|thief|witch(?:es)?)|' . $adjS . '{1,2} (?:' . $animals . '))\b'
        . '|' . $adj . '? (?:stupid|dumb|useless|worthless)(?= \|))/u';
    // "Stupid cow, go home": an insult that opens the clause is aimed at someone ("the lazy dog" is not, it does not open the clause)
    $open = 'stupid|dumb|ugly|useless|worthless|shameless|stinking|pathetic|disgusting|nasty|rotten|brainless|hopeless|filthy|damn|bloody'; // not "fat pig for sale", "lazy dog"
    if (ugc_match('/ \| (?:' . $open . ') (?:(?:' . $open . ') )?(?:cow|pig|dog|goat|donkey|monkey|rat|snake|animal|beast|devil|liar|thief|witch|clown|fool|idiot|moron)\b/u', $clauses)) {
        return true;
    }
    if (!ugc_all($re, $clauses, $m, PREG_OFFSET_CAPTURE)) {
        return false;
    }
    foreach ($m[0] as $hit) {
        $before = substr($clauses, max(0, $hit[1] - 16), min($hit[1], 16)); // byte offsets: the words before are plain ASCII
        if (!preg_match('/\b(?:thank|thanks|love|bless|miss|tell|told|show|give|gave|ask|answer|hear|heard|see|saw|let|offer|wish|owe|send|sent|pay|paid|promise|bring|brought|teach|taught|cheer|salute|congratulat|appreciat|warn|remind|inform)\w* $/', $before)) {
            return true;
        }
    }
    return false;
}

/**
 * Leet view: inside a token that has 2+ ASCII letters (or one letter and 2+ of @ $), 0 1 3 4 5 7 @ $ become o i e a s t a s
 * ("sh1t", "p0rn", "a$$hole", "a$$"), and a ! between letters becomes i ("sh!t"). $oneIsL reads 1 as l instead ("ki11", "wi11").
 */
function ugc_leet(string $n, bool $oneIsL = false): string
{
    if (!preg_match('/[0-9@$!]/', $n)) {
        return $n;
    }
    $map = ['0' => 'o', '1' => $oneIsL ? 'l' : 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's'];
    $n = preg_replace('/(?<=[a-z])!+(?=[a-z])/', 'i', $n) ?? $n;
    return preg_replace_callback('/\S+/u', static function (array $m) use ($map): string {
        $tok = $m[0];
        $letters = preg_match_all('/[a-z]/', $tok);
        if (!preg_match('/[0-9@$]/', $tok) || ($letters < 2 && !($letters === 1 && preg_match_all('/[@$]/', $tok) >= 2))) {
            return $tok;
        }
        return strtr($tok, $map);
    }, $n) ?? $n;
}

/**
 * Squashed text: a run of 3+ single characters split by one or two separators is joined into one word ("f u c k", "f.u.c.k", "s_h_i_t").
 * When the run starts with a, i or u, the word without that first letter is added too ("a f u c k" -> "afuck fuck").
 */
function ugc_squash(string $n): string
{
    return preg_replace_callback(
        '/(?<![\p{L}\p{N}])[\p{L}\p{N}](?:[ \t._\-*|,+\/\\\\]{1,2}[\p{L}\p{N}]){2,}(?![\p{L}\p{N}])/u',
        static function (array $m): string {
            $joined = preg_replace('/[^\p{L}\p{N}]+/u', '', $m[0]) ?? $m[0];
            return preg_match('/^[aiu][^\p{L}\p{N}]/u', $m[0]) && mb_strlen($joined) > 3 ? $joined . ' ' . mb_substr($joined, 1) : $joined;
        },
        $n
    ) ?? $n;
}

/** Masked view: only the words that contain a mask character between or after letters ("f*ck", "sh#t", "f***"); null when there are none. */
function ugc_masked(string $n): ?string
{
    $mask = '/\p{L}[*#]+\p{L}|\p{L}[*#]{2,}/u';
    if (!preg_match($mask, $n)) {
        return null;
    }
    $keep = [];
    foreach (preg_split('/[^\p{L}\p{N}*#]+/u', $n, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $tok) {
        // no listed word is longer than 30 characters; long mask runs are shortened so the wildcard regex stays fast
        if (mb_strlen($tok) <= 30 && preg_match($mask, $tok)) {
            $keep[] = preg_replace('/[*#]{5,}/', '****', $tok);
        }
    }
    return $keep ? ' ' . implode(' ', $keep) . ' ' : null;
}

/** All the text views the word lists are matched on (the plain words view is always first). */
function ugc_views(string $n): array
{
    $sq = ugc_squash($n);
    $views = [];
    foreach ($sq === $n ? [$n] : [$n, $sq] as $b) {
        $views[] = ugc_words($b);
        if (preg_match('/[0-9@$!]/', $b)) {
            $views[] = ugc_words(ugc_leet($b));
            if (str_contains($b, '1')) {
                $views[] = ugc_words(ugc_leet($b, true));
            }
        }
    }
    $masked = ugc_masked($n);
    if ($masked !== null) {
        $views[] = $masked;
    }
    return array_values(array_unique($views));
}

/** Drops quoted speech that a reporting verb introduces (said "I will kill you"), so news retellings are not read as threats. */
function ugc_unquote(string $n): string
{
    if (!str_contains($n, '"')) {
        return $n;
    }
    $say = 'said|says|saying|told|tells|shouted|shouting|screamed|yelled|threatened|warned|texted|wrote|messaged|claimed|reportedly|allegedly|alleged|quoted|replied|stated|testified|added';
    $s = preg_replace('/\b(?:' . $say . ')\b[^"\n]{0,80}"[^"]{1,200}"/u', ' ', $n) ?? $n;
    return preg_replace('/"[^"]{1,200}",?\s+(?:he|she|they|the \w+|\w+ \w+|\w+)\s+(?:' . $say . ')\b/u', ' ', $s) ?? $s;
}

/** Set when a regex could not run (backtrack limit, bad input): the comment is then held rather than trusted. */
function ugc_pcre_error(?bool $set = null): bool
{
    static $failed = false;
    if ($set !== null) {
        $failed = $set;
    }
    return $failed;
}

function ugc_match(string $re, string $s): bool
{
    $r = preg_match($re, $s);
    if ($r === false) {
        ugc_pcre_error(true);
        return false;
    }
    return $r === 1;
}

/** preg_match_all that reports a regex failure like ugc_match does. */
function ugc_all(string $re, string $s, ?array &$m = null, int $flags = PREG_PATTERN_ORDER): bool
{
    $r = preg_match_all($re, $s, $m, $flags);
    if ($r === false) {
        ugc_pcre_error(true);
        return false;
    }
    return $r > 0;
}

/**
 * One list entry -> [regex body, prefix?]. Every run of the same letter in the entry may be stretched ("fuck" matches "fuuuck") but a
 * doubled letter must stay doubled, so "nigger" does NOT match the country "Niger". $wild lets * and # stand in for any letter ("f*ck").
 * Entries shorter than 3 characters are ignored.
 */
function ugc_term_piece(string $term, bool $wild = false): ?array
{
    $term = trim($term);
    $prefix = str_ends_with($term, '*');
    // plain lower-case entries (the built-in lists) skip the Unicode clean-up, which keeps the first check of a request quick
    $t = preg_match('/^[a-z0-9 *]+$/', $term)
        ? trim(preg_replace('/[^a-z0-9]+/', ' ', $term) ?? '')
        : trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', ugc_norm($term)) ?? '');
    if (mb_strlen($t) < 3) {
        return null;
    }
    $words = [];
    foreach (explode(' ', $t) as $word) {
        $re = '';
        preg_match_all('/(.)\1*/su', $word, $runs, PREG_SET_ORDER);
        foreach ($runs as $r) {
            $len = mb_strlen($r[0]);
            $c = $wild ? '[' . preg_quote($r[1], '/') . '*#]' : preg_quote($r[1], '/');
            $re .= $c . ($len > 1 ? '{' . $len . ',}' : '+');
        }
        $words[] = $re;
    }
    return [implode(' ', $words), $prefix];
}

/** One list entry -> regex. No match inside longer words (Scunthorpe, class); a trailing * lifts the end-of-word rule. */
function ugc_term_regex(string $term): ?string
{
    $p = ugc_term_piece($term);
    return $p === null ? null : '/(?<![\p{L}\p{N}])' . $p[0] . ($p[1] ? '' : '(?![\p{L}\p{N}])') . '/u';
}

/** The whole list as one regex (compiled once per list). */
function ugc_terms_regex(array $terms, bool $wild = false): ?string
{
    static $cache = [];
    $key = ($wild ? 'w' : 'p') . md5(implode("\n", $terms));
    if (!array_key_exists($key, $cache)) {
        $alts = [];
        foreach ($terms as $term) {
            $p = ugc_term_piece((string) $term, $wild);
            if ($p !== null) {
                $alts[$p[0] . ($p[1] ? '' : '(?![\p{L}\p{N}])')] = true;
            }
        }
        $cache[$key] = $alts ? '/(?<![\p{L}\p{N}])(?:' . implode('|', array_keys($alts)) . ')/u' : null;
    }
    return $cache[$key];
}

/** Admin textarea -> clean list: one entry per line, blank lines and "#" comments dropped, max 300 entries. */
function ugc_lines(?string $setting): array
{
    $out = [];
    foreach (preg_split('/\R/u', (string) $setting) ?: [] as $line) {
        $line = trim($line);
        if ($line !== '' && $line[0] !== '#') {
            $out[] = $line;
        }
        if (count($out) >= 300) {
            break;
        }
    }
    return $out;
}

function ugc_any_term(array $terms, array $views): bool
{
    $plain = ugc_terms_regex($terms);
    $wild = null;
    foreach ($views as $view) {
        if ($plain !== null && ugc_match($plain, $view)) {
            return true;
        }
        if (strpbrk($view, '*#') !== false) { // only the masked view still has mask characters
            $wild ??= ugc_terms_regex($terms, true);
            if ($wild !== null && ugc_match($wild, $view)) {
                return true;
            }
        }
    }
    return false;
}

function ugc_host_allowed(string $host, array $allowed): bool
{
    $host = rtrim(strtolower($host), '.,;:!?)');
    $host = preg_replace('/^www\./', '', $host);
    foreach ($allowed as $d) {
        $d = strtolower(trim($d));
        if ($d !== '' && ($host === $d || str_ends_with($host, '.' . $d))) {
            return true;
        }
    }
    return false;
}

/** Cyrillic and Greek look-alikes of Latin letters, upper and lower case ("scamsіte.com" with a Cyrillic і), for the link and e-mail checks, which keep letter case. */
function ugc_fold_lookalikes(string $s): string
{
    static $map = null;
    if ($map === null) {
        $map = UGC_HOMOGLYPHS;
        foreach (UGC_HOMOGLYPHS as $from => $to) {
            $map[mb_strtoupper($from, 'UTF-8')] = strtoupper($to);
        }
    }
    return strtr($s, $map);
}

/** Text for the link and e-mail checks: keeps letter case (a capitalised TLD after a missing space is not a link), undoes "scamsite dot com". */
function ugc_link_text(string $s): string
{
    $s = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}\x{FEFF}\x{00AD}]/u', '', $s) ?? $s;
    if (class_exists('Normalizer')) {
        $k = Normalizer::normalize($s, Normalizer::FORM_KC);
        if ($k !== false) {
            $s = $k;
        }
    }
    if (preg_match('/[^\x00-\x7F]/', $s)) {
        $s = ugc_fold_lookalikes($s);
    }
    $s = strtr($s, ["\u{3002}" => '.', "\u{FF0E}" => '.', "\u{FF61}" => '.', "\u{2024}" => '.', "\u{FE52}" => '.']);
    $tld = '(?:' . UGC_TLDS_FIRM . '|' . UGC_TLDS_WORD . '|(?i:' . UGC_TLDS_FIRM3 . '))(?![A-Za-z0-9-])';
    // "scamsite dot com", "scamsite . com", "scamsite[.]com", "scamsite (dot) com"; only before a lower-case TLD or a .COM/.Net-style one
    $s = preg_replace('/(?<=[A-Za-z0-9])(?:\s+(?i:dot)\s+|\s+\.\s*|\s*[\[({<]\s*(?:\.|(?i:dot))\s*[\])}>]\s*)(?=' . $tld . ')/u', '.', $s) ?? $s;
    $s = preg_replace('/\s*[\[({<]\s*(?i:at)\s*[\])}>]\s*(?=[A-Za-z0-9-]+\.)/u', '@', $s) ?? $s;
    return preg_replace('/\bhxxp(s?):\/\//i', 'http$1://', $s) ?? $s;
}

/** One of the station's own pages on a host that is otherwise not allowed (t.me/oromatv, not t.me/oromatv_free_money). */
function ugc_own_link(string $url): bool
{
    $u = strtolower(preg_replace('~^(?:https?://)?(?:www\.)?~i', '', $url) ?? $url);
    foreach (UGC_ALLOWED_LINKS as $own) {
        if (str_starts_with($u, $own) && ($u === $own || strpbrk($u[strlen($own)], '/?#.,;:!)') !== false)) {
            return true;
        }
    }
    return false;
}

/** True when this URL goes to a host that is not allowed, or uses an allowed site (l.facebook.com/l.php, youtube.com/redirect) to reach another one. */
function ugc_url_bad(string $url, array $allowed): bool
{
    if (ugc_own_link($url)) {
        return false;
    }
    preg_match('~^(?:https?://)?([^/:?#\s]+)(.*)$~is', $url, $h);
    if (!ugc_host_allowed($h[1] ?? $url, $allowed)) {
        return true;
    }
    return ($h[2] ?? '') !== '' && preg_match('~(?i:https?(?::|%3a)(?:/|%2f){2}|%68%74%74%70|/redirect\b|/l\.php\b|/away\b|[?&](?:url|redirect(?:_uri)?|target|goto|dest|continue)=)~', $h[2]) === 1;
}

/** $s is the ugc_link_text() of the comment. */
function ugc_has_bad_link(string $s, array $allowed): bool
{
    // 1. anything that says it is a link
    if (ugc_all('~(?i:https?://|www\.)[^\s<>"\']+~u', $s, $found)) {
        foreach ($found[0] as $url) {
            if (ugc_url_bad($url, $allowed)) {
                return true;
            }
        }
    }
    // 2. shorteners, with or without a path
    if (ugc_all('~(?<![@\w.-])((?i:' . UGC_SHORTENERS . '))(?![A-Za-z0-9-])([/?#][^\s<>"\']*)?~u', $s, $found, PREG_SET_ORDER)) {
        foreach ($found as $m) {
            if (!ugc_host_allowed($m[1], $allowed) && !ugc_own_link($m[1] . ($m[2] ?? ''))) {
                return true;
            }
        }
    }
    // 3. a bare "name.tld"
    $re = '~(?<![@\w.-])((?:[A-Za-z0-9][A-Za-z0-9-]{0,62}\.)+)((?i:' . UGC_TLDS_FIRM . '|' . UGC_TLDS_WORD . '))(?![A-Za-z0-9-])((?::\d{1,5})?(?:[/?#][^\s<>"\']*)?)~u';
    if (ugc_all($re, $s, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($found as $m) {
            [$label, $tld, $rest] = [$m[1][0], $m[2][0], $m[3][0]];
            $low = strtolower($tld);
            if ($low === 'ml' && $rest === '' && preg_match('~^[0-9.]+$~', $label)) { // "500.ml" is a volume, not a Mali domain
                continue;
            }
            if ($low === 'net' && $rest === '' && preg_match('~^(?:asp|vb)\.$~i', $label)) { // ASP.NET, VB.NET
                continue;
            }
            $isWord = preg_match('~^(?:' . UGC_TLDS_WORD . ')$~', $low) === 1;
            $cue = substr($s, max(0, $m[0][1] - 40), min($m[0][1], 40));
            // signs that it is meant as a link: a path, three labels, or a letter plus a digit or hyphen in the name ("1.vote" and "2023.live" are only a list and a year)
            $strong = $rest !== '' || substr_count($label, '.') >= 2 || (preg_match('/[A-Za-z]/', $label) && preg_match('/[0-9-]/', $label));
            if ($tld === $low || ($tld === strtoupper($tld) && strlen($tld) >= 3)) {
                // typed like a domain ("scamsite.com", "SCAMSITE.COM"); an ordinary-word TLD still needs a sign
                if ($isWord && !$strong && !preg_match(UGC_CUE_WIDE, $cue) && !preg_match('~(?:^|\n)[ \t]*' . preg_quote($m[0][0], '~') . '[ \t]*(?:\n|$)~', $s)) {
                    continue; // a lone "joinus.vip" is a link, "great show.live" is not
                }
            } else {
                // capitalised or mixed case: "Thank you.Ug", "Well done.Online", "channel.Net speed" are a missing space after a full stop. It is a
                // link with a sign, or when it is a .Com/.Net/.Org-style TLD that does not run on into a lower-case word ("Evil.Com", "go to Evil.Com")
                $runsOn = preg_match('/^[ \t]+[a-z]/', substr($s, $m[0][1] + strlen($m[0][0]), 8)) === 1;
                $firm = !$isWord && strlen($low) >= 3;
                if (!$strong && !preg_match(UGC_CUE_NARROW, $cue) && (!$firm || $runsOn)) {
                    continue;
                }
            }
            if (ugc_url_bad($m[0][0], $allowed)) {
                return true;
            }
        }
    }
    return false;
}

/** E-mail address or phone number in the text (spam, and not something to publish about a stranger). */
function ugc_has_contact(string $n, string $plain, string $linkText, array $allowed): bool
{
    // not inside a longer number ("UGX 5,0772...") and not ending in more digits
    $pre = '(?<![\p{L}\p{N}])(?<!\d,)(?<!\d\.)';
    $post = '(?!\d|,\d)';
    // Ugandan mobile (07..) and landline (03.., 04..), any spacing, "+256", a letter O for the zero, spaced-out digits.
    // "256 700 000 000" shillings ends in zeros, a phone number does not.
    if (preg_match('/[\x{0660}-\x{0669}\x{06F0}-\x{06F9}]/u', $n)) { // Arabic-Indic digits
        $n = preg_replace_callback('/[\x{0660}-\x{0669}\x{06F0}-\x{06F9}]/u', static fn(array $d): string => (string) (mb_ord($d[0], 'UTF-8') - (mb_ord($d[0], 'UTF-8') >= 0x6F0 ? 0x6F0 : 0x660)), $n) ?? $n;
    }
    if (ugc_all('/' . $pre . '(?:(?:\+|00)?256[\s.-]?(?:\(0\)[\s.-]?)?|[0o][\s.-]?)\(?[347](?:[\s.-]?\d){8}' . $post . '/u', $n, $m)) { // "+256 (0) 772 ...", "00256 772 ..." too
        foreach ($m[0] as $num) {
            if (preg_match('/^(?:0[1-9]|[12]\d|3[01])[.\/ -](?:0[1-9]|1[0-2])[.\/ -](?:19|20)\d{2}\b/', $num)) { // a date and a time ("07-11-2024 14:30") has the shape of a number
                continue;
            }
            if (preg_match('/(?<!\d)\d-\d[\s,;]+\d-\d[\s,;]+\d-\d(?!\d)/', $num)) { // match scores: "0-3 0-4 0-7 1-1"
                continue;
            }
            if (!preg_match('/0{5}$/', preg_replace('/\D+/', '', $num))) {
                return true;
            }
        }
    }
    if (ugc_all('/(?<![\p{L}\p{N}])\+\d{1,3}[\s.-]?\(?\d{2,4}\)?[\s.-]?\d{3}[\s.-]?\d{3,4}' . $post . '/u', $n, $m)) { // international
        foreach ($m[0] as $num) {
            if (!preg_match('/0{5}/', preg_replace('/\D+/', '', $num))) { // "+1 000 000 000 shillings" is an amount
                return true;
            }
        }
    }
    // "WhatsApp me on 772123456": nine digits without the leading zero count only next to a word that asks for contact (a bare 9-digit number is ordinary)
    if (ugc_all('/(?<![\p{L}\p{N}.,])(?<!\d[\s.-])7\d{2}[\s.-]?\d{3}[\s.-]?\d{3}' . $post . '/u', $n, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as [$num, $off]) {
            if (!preg_match('/0{5}$/', preg_replace('/\D+/', '', $num))
                && preg_match('/\b(?:whatsapp|call|text|sms|tel|phone|number|contact|dial|reach|ring|mobile)\b/u', substr($n, max(0, $off - 30), 30) . ' ' . substr($n, $off + strlen($num), 20))) {
                return true;
            }
        }
    }
    // a number spelled out, or half spelled out: "zero seven seven two one two three four five six", "0 seven 7 2 1 2 3 4 5 6"
    if (ugc_all('/ (?:(?:zero|oh|one|two|three|four|five|six|seven|eight|nine|\d) ){8,}/u', $plain, $runs)) {
        foreach ($runs[0] as $run) {
            if (preg_match('/[a-z]/', $run)) {
                return true;
            }
        }
    }
    if (ugc_match('/[a-z0-9._%+-]+\s?@\s?(?:gmail|yahoo|hotmail|outlook|icloud|proton(?:mail)?)\.[a-z]{2,}/u', $n)) { // "john @gmail.com"
        return true;
    }
    foreach ([$n, mb_strtolower($linkText, 'UTF-8')] as $text) {
        if (ugc_all('/[a-z0-9._%+-]+@([a-z0-9-]+(?:\.[a-z0-9-]+)+)/u', $text, $mails)) {
            foreach ($mails[1] as $host) {
                if (!ugc_host_allowed($host, $allowed)) {
                    return true;
                }
            }
        }
    }
    return false;
}

function ugc_filter_check(string $name, string $comment, array $extraTerms = [], array $extraDomains = []): ?string
{
    ugc_pcre_error(false);
    $tag = ugc_filter_scan($name, $comment, $extraTerms, $extraDomains);
    // a regex that could not run means the comment was not fully checked: hold it
    return $tag ?? (ugc_pcre_error() ? 'spam' : null);
}

function ugc_filter_scan(string $name, string $comment, array $extraTerms, array $extraDomains): ?string
{
    $raw = $name . "\n" . $comment;
    $n = ugc_norm($raw);
    // "Ass. Prof. Okello" is the title Assistant Professor (common in Uganda), not the word "ass"
    $n = preg_replace('/\bass(?=\.\s*(?:prof|lecturer|director|dir|commissioner|registrar)\b|\s+prof\b)/u', 'asst', $n) ?? $n;
    if (preg_match('/\b(?:donkeys?|balaam|mules?|oxen|camels?|livestock)\b/', $n)) { // "Balaam's talking ass": the animal
        $n = preg_replace('/\bass\b/u', 'asx', $n) ?? $n;
    }
    $views = ugc_views($n);
    $plain = $views[0];

    // 1. threats and incitement (a quoted threat after "said" is a retelling, not a threat)
    $t = ugc_unquote($n);
    foreach ($t === $n ? $views : ugc_views($t) as $v) {
        foreach (UGC_THREAT_PATTERNS as $re) {
            if (ugc_match($re, $v)) {
                return 'threat';
            }
        }
    }
    $sent = ugc_sentences($t);
    foreach (UGC_THREAT_SENTENCE_PATTERNS as $re) {
        if (ugc_match($re, $sent)) {
            return 'threat';
        }
    }
    // 2. profanity, slurs, sexual terms, then the admin's own list
    if (ugc_any_term(UGC_DEFAULT_TERMS, $views)) {
        return 'profanity';
    }
    foreach (UGC_SLUR_PATTERNS as $re) {
        if (ugc_match($re, $plain)) {
            return 'profanity';
        }
    }
    $clauses = ugc_clauses($n);
    foreach (UGC_INSULT_PATTERNS as $re) {
        if (ugc_match($re, $clauses)) {
            return 'profanity';
        }
    }
    if (ugc_vocative_insult($clauses)) {
        return 'profanity';
    }
    if ($extraTerms && ugc_any_term($extraTerms, $views)) {
        return 'custom';
    }
    // 3. links to domains that are not allowed
    $allowed = array_merge(UGC_ALLOWED_DOMAINS, array_map('strtolower', $extraDomains));
    $linkText = ugc_link_text($raw);
    if (ugc_has_bad_link($linkText, $allowed)) {
        return 'link';
    }
    // 4. phone numbers and e-mail addresses
    if (ugc_has_contact($n, $plain, $linkText, $allowed)) {
        return 'contact';
    }
    // 5. scam / betting / "message me" phrases (also with the letters spaced out: "f o r e x")
    $squashed = ugc_squash($n);
    $spamViews = array_values(array_unique([$plain, ugc_words($squashed)]));
    foreach ($spamViews as $v) {
        foreach (UGC_SPAM_PATTERNS as $re) {
            if (ugc_match($re, $v)) {
                return 'spam';
            }
        }
    }
    if (ugc_any_term(UGC_SPAM_TERMS, $spamViews)) {
        return 'spam';
    }
    // letters spaced out are an evasion in themselves, so the phrases that are too ordinary to hold in plain text count here
    if ($squashed !== $n && ugc_any_term(UGC_SPAM_TERMS_SPACED, [ugc_words($squashed)])) {
        return 'spam';
    }
    return null;
}
