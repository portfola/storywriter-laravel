<?php

namespace Database\Seeders;

use App\Models\Heirloom\Session;
use App\Models\Heirloom\Subject;
use App\Models\Heirloom\Transcript;
use App\Models\User;
use Illuminate\Database\Seeder;

class HeirloomTestTranscriptSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'timothybenjaminbeckett@gmail.com')->firstOrFail();

        $subject = Subject::create([
            'user_id'              => $user->id,
            'name'                 => 'Arthur Calloway',
            'birth_year'           => 1943,
            'places_lived'         => "King's Lynn, Norfolk; Plymouth, Devon; Edinburgh, Scotland (sabbatical year)",
            'education_profession' => "BSc Marine Biology, University of Hull (1965); PhD, University of Plymouth (1969); Research Scientist, Marine Biological Association of the UK, Plymouth (1969–1998)",
            'family_structure'     => "Married Margaret Calloway née Trott (1968; she died 2019). Two children: Clare (b. 1970) and David (b. 1973).",
            'life_chapters'        => "Childhood in King's Lynn, 1943–1961; undergraduate and doctoral years in Hull and Plymouth, 1961–1969; early career at the MBA, 1969–1975; Norwegian Sea expedition and publication, 1974–1976; senior research years and consultancy, 1976–1998; retirement in Plymouth, 1998–present.",
            'interests'            => "Birdwatching (waders especially), maritime history, crossword puzzles, walking the coastal path.",
        ]);

        $sessions = [
            [
                'title'      => 'Childhood and School',
                'transcript' => $this->session1(),
            ],
            [
                'title'      => 'Career and the Norwegian Sea Expedition',
                'transcript' => $this->session2(),
            ],
            [
                'title'      => 'Work and Family',
                'transcript' => $this->session3(),
            ],
        ];

        foreach ($sessions as $data) {
            $session = Session::create([
                'user_id'    => $user->id,
                'subject_id' => $subject->id,
                'title'      => $data['title'],
                'status'     => 'transcribed',
            ]);

            Transcript::create([
                'session_id'      => $session->id,
                'user_id'         => $user->id,
                'transcript_text' => $data['transcript'],
                'status'          => 'completed',
                'source'          => 'manual',
                'language'        => 'en',
            ]);
        }
    }

    private function session1(): string
    {
        return <<<'TRANSCRIPT'
[Recording begins. Arthur is seated at the kitchen table. Clare has placed a small recorder between them. Morning light. A cup of tea in front of Arthur, untouched.]

Clare: Right, Dad. I thought we'd start at the very beginning. Where did you grow up?

Arthur: King's Lynn. In Norfolk.

Clare: Can you describe it a bit — what was it like when you were a child?

Arthur: [pause] It was a working town. People worked. The docks were still busy then — coal coming in, grain going out. It smelled of the sea and of engine oil and sometimes of fish. Not picturesque in the way people imagine Norfolk now. It was functional. Which I don't mean as a criticism.

Clare: Your father worked at the docks, didn't he?

Arthur: For the port authority. He was an operations supervisor — kept the loading schedules, made sure things moved when they were meant to move. He was very good at it. He was the sort of man who was always slightly early.

Clare: What was he like, as a person?

Arthur: Methodical. He didn't waste words. He was fair to the men who worked for him, which I think is about the most you can say for someone in that position.

Clare: And your mother?

Arthur: She ran the house. She had a very precise memory — could tell you what she'd paid for butter in 1952 without a second's hesitation. I don't know what I'd have done without that kind of precision around me as a child. It felt like the world was accurately described.

Clare: [laughs softly] That does sound familiar. What do you remember most about the house itself?

Arthur: The smell of it, if I'm honest. Coal fires in winter. My mother used a great many bay leaves in everything — I've never worked out why. And the sound of the wind. King's Lynn is flat country, and the wind comes off The Wash without anything to slow it down. You always knew when the weather was turning before it turned. The windows would start to rattle in a particular way.

Clare: Did you spend a lot of time near the water?

Arthur: A great deal of it. The Wash is quite remarkable, actually. Most people don't understand it. It looks like nothing — mud flats, grey water, overcast sky — but there's an enormous amount happening. The tides come in very fast. The birds— [pause] I used to go out to the flats with a school friend, Robert Crane, and we'd watch the waders. Knot, mostly. They arrive in enormous flocks — hundreds of thousands — and they move like a single organism. It's one of the more remarkable things I've seen, and I have since seen quite a lot.

Clare: Did you know even then that that was what interested you — the natural world?

Arthur: I didn't think of it as an interest at the time. It was just where I went.

Clare: Let's talk about school. You went to the grammar school?

Arthur: King Edward VII Grammar School. I sat the eleven-plus in 1954 and passed. My father was pleased. He hadn't gone himself.

Clare: Did you enjoy school?

Arthur: [pause] Enjoy is a complicated word for school. I was good at it. That's different. Science especially. There was a biology teacher — Mr. Featherstone — who was exceptional. Most biology teaching then was taxonomy, rote learning of classifications. He wasn't like that. He'd bring in specimens. He had a connection to someone at the Norfolk Naturalists Trust. We did actual field observations — kept notebooks, made measurements.

Clare: He sounds like he made a real impression on you.

Arthur: He set a standard for what rigour looked like. I must have been twelve or thirteen. I remember he told us once that a bad observation was worse than no observation at all, because it entered the literature and stayed there. [pause] I've thought about that quite a number of times since.

Clare: Did you know then that you wanted to go into science professionally?

Arthur: No. I considered the merchant navy for a while. Then engineering. It wasn't until I was about fifteen that I found a book at the town library — a survey of North Sea invertebrate populations. Very dry book, actually. Distribution maps, sampling data. But something about the precision of it — the attempt to describe something that kept moving and changing — seemed to me like worthwhile work. The kind of thing worth spending a life on.

Clare: Do you remember who wrote it?

Arthur: [thinks] It was in the tradition of Alister Hardy's work, though I may be misremembering the actual name. Hardy wrote The Open Sea — that was later. The book I read was something similar in character. Unglamorous survey work, done very carefully.

Clare: And from there it was: university?

Arthur: There wasn't much question about that. Mr. Featherstone suggested Hull — they had a strong marine science programme, good connections to the North Sea. My father thought a university degree was a somewhat unusual ambition but didn't object to it. My mother said it was very far away.

Clare: How far was it?

Arthur: About a hundred and twenty miles.

Clare: [laughs] And was he right? Was Hull the right place?

Arthur: Hull was exactly right.

[Pause. Arthur picks up his tea for the first time.]

Clare: One last thing for today — is there anything about growing up there that you think made you who you are? That you carry with you?

Arthur: [long pause] The flatness, possibly. When the landscape doesn't give you much to look at, you learn to look more carefully at what's there. That may be fanciful. [pause] The wind. Always knowing the weather was turning before it did. I've been told I have that quality in other contexts. I'm not sure it's always appreciated.

[Recording ends. 52 minutes recorded. This is an edited excerpt.]
TRANSCRIPT;
    }

    private function session2(): string
    {
        return <<<'TRANSCRIPT'
[Recording begins. Arthur has a cup of tea. Clare has set the recorder between them on the kitchen table. Early afternoon.]

Clare: I want to hear about the career properly. How did you actually get started — after the PhD?

Arthur: I joined the Marine Biological Association in Plymouth in 1969. Junior research position. The work was benthic survey methodology — sediment sampling, mostly. Classification of what was living on and in the sea floor at various depths. It was not glamorous work.

Clare: Were you happy with it?

Arthur: I was doing what I'd set out to do. Whether that constitutes happiness — I'm not sure that's the right framing. I was useful. The surveys were needed and not many people wanted to do them.

Clare: You were at the MBA for nearly thirty years.

Arthur: Twenty-nine. Yes.

Clare: Is there one project — one piece of work — that stands out when you look back?

Arthur: [pause] The Norwegian Sea survey. 1974.

Clare: Tell me about that.

Arthur: We were studying the distribution of cold-water coral — Lophelia pertusa, though it's been reclassified since. There had been incidental observations from trawlermen, from oil exploration sonar — fragments, really. But nobody had conducted a systematic survey of where these formations actually were, at depth, and in what concentrations. The prevailing assumption was that they were sparse. Occasional structures. My colleague Peter Arnott and I thought that was wrong.

Clare: What made you think that?

Arthur: Peter's work on cold-water upwelling. He was a physical oceanographer — interested in how deep, oxygenated water gets pushed up along certain ridge systems. His theory was that the coral distribution would follow those upwelling zones precisely. I found the argument convincing. The trawler records, when you mapped them, clustered in a way that was consistent with his model.

Clare: So you designed a study around it.

Arthur: We put together a proposal. It took two attempts to get funded. The second application was more specific about the methodology — the committee wanted procedure, not idea.

Clare: What ship did you go out on?

Arthur: RRS Discovery. Out of Southampton. We were at sea for eight weeks. Forty-one days of actual survey work, plus passage time.

Clare: What was it like being on the ship?

Arthur: [pause] You want the accurate answer or the romantic one?

Clare: Both, if they're different.

Arthur: The accurate one is: ship time is repetitive, physically uncomfortable, and extremely focused. You run the equipment on a schedule, you record data, you check the equipment, you run it again. The North Atlantic in October is cold and frequently rough. People get tired and irritable. There are small grievances that become large ones. But the focus is also — it's clarifying. There's nothing else to think about. The problem is the whole world.

Clare: And the romantic one?

Arthur: [long pause] There are moments at night, when the equipment is running and you have nothing to do but wait. If the sea is calm enough you can stand on deck. The bioluminescence in the wake. The stars if there's no cloud. You are in the middle of something very large that doesn't care about you in the slightest. [pause] I find that restful, actually. I've always found it restful.

Clare: Did you find what you were looking for?

Arthur: We found considerably more. The acoustic mapping showed reef structures — I use "reef" loosely, it's not tropical coral — along a ridge system in the Norwegian Sea that hadn't been documented. The concentrations were an order of magnitude higher than existing estimates. We published in 1976. That data was later cited in arguments about where North Sea drilling should not take place, in the 1980s.

Clare: That must have felt significant — to see your science used like that.

Arthur: It felt correct. We had been right, and the data confirmed it. That doesn't happen as often as people assume.

Clare: Was there a single moment on the expedition that you remember most clearly?

Arthur: [pause] Day thirty-one. We'd had good acoustic returns all week but nothing confirmed — nothing you could hold in your hand. Peter and I had been disagreeing, quietly, about whether we'd miscalculated the upwelling zone by approximately fifteen kilometres. I thought we had. He thought the original model was sound. And then on day thirty-one we brought up a sediment core with Lophelia fragments going down more than forty centimetres. Which meant the formation had been there for centuries. Possibly much longer.

Clare: That's extraordinary.

Arthur: Peter came and found me in the ship's mess. He said, "You were right about the fifteen kilometres." That's the moment I remember most clearly from the whole expedition.

Clare: Not the discovery itself — his saying so.

Arthur: The science requires the acknowledgement. Otherwise it's just looking at things.

Clare: Peter Arnott — is he still around?

Arthur: He died in 2011. Pancreatic cancer. He was a very good scientist and a perfectly decent man. [pause] I miss arguing with him. He was one of the few people I've known who could be wrong gracefully.

Clare: Do you miss the fieldwork? Being at sea?

Arthur: I miss the clarity of it. I don't miss the physical discomfort, which has become more significant in retrospect. I miss knowing exactly what I was trying to solve and having the means to solve it in front of me. That's harder to arrange on land, as it turns out.

Clare: If a young marine biologist asked you what they should do — what would you tell them?

Arthur: Learn to be bored. The people who think the excitement is the point don't last, or they don't produce rigorous work. The willingness to record the same measurement correctly for the four hundredth time — that's what makes good science. The excitement is a side effect. It shouldn't be the goal.

[Pause. Clare reaches over and turns off the recorder.]

[Clare restarts it briefly.]

Clare: One more. Is there anything from that expedition — or from those years — that you never published? Something that got away?

Arthur: [pause] There was a second ridge system, further north, that we didn't have time to survey properly. The data was suggestive but not sufficient. I wrote it up in a working paper, but it was never developed. I don't know what's there. [quietly] I've thought about it since. I expect someone has surveyed it by now and I just haven't followed the literature closely enough.

[Recording ends.]
TRANSCRIPT;
    }

    private function session3(): string
    {
        return <<<'TRANSCRIPT'
[Recording begins. A longer pause than usual before Arthur speaks. Clare has told him before starting that this session will cover some harder ground. He has agreed.]

Clare: I want to talk about something that's — I'm not quite sure how to put it.

Arthur: Go ahead.

Clare: When David and I were young, you were away a lot. The expeditions, the conferences, the consultancy trips. I want to try to understand that period from your perspective. What it was like for you.

Arthur: [pause] All right.

Clare: How much of the year were you typically away? When I was — say, between seven and fifteen?

Arthur: [thinks] In a productive year — and most of them were productive — I would estimate eight to twelve weeks at sea or in the field. Then perhaps another three or four weeks for conferences and collaborative visits. Say three months in total. On average. Some years more.

Clare: That's quite a lot.

Arthur: It was what the work required. The fieldwork was seasonal. The conference schedules were fixed. I didn't set the calendar unilaterally.

Clare: I know that. I'm not saying you did. What I'm asking is — what do you remember of us during that time? What did you come home to?

Arthur: [long pause] Your mother managed things very well. The house was orderly. You and David had your routines. She was capable of — she didn't require a great deal of input from me to keep things functioning.

Clare: Do you remember anything specific? About us? When we were small?

Arthur: [quietly] I remember David's voice. When he was very small he used very formal words — slightly too large for him. Completely serious about it. [pause] I remember you learning to ride a bicycle. In the back garden. I wasn't there for that. Your mother told me. She said you'd got on one afternoon and simply done it, and refused to let David help you. She thought that was very characteristic.

Clare: [small pause] Yeah. That sounds right.

Arthur: [clears his throat] I'm aware that many of the things I remember clearly are things your mother described to me rather than things I witnessed directly. I notice that. I don't quite know what to do with it.

Clare: Do you think — and you don't have to answer — do you think the work was more important to you than we were?

Arthur: [long silence] That's not — I don't think that framework is quite right.

Clare: What's the right framework?

Arthur: [slowly] At the time, the work was what I understood. I knew how to do it. I knew when I was doing it correctly. [pause] I loved you and David. But I wasn't confident that I knew how to be present in a way that was actually useful. Your mother knew what you needed. I often didn't. Going away was — partly, it was easier. I don't say that proudly.

Clare: That's honest.

Arthur: It may also be a convenient explanation. I'm aware of that distinction.

Clare: [pause] Did Mum ever say anything to you about it? About the absences?

Arthur: Once, directly. About 1979. She said she had taken on a life she hadn't entirely chosen, and she needed me to understand that. [pause] I said I understood. I don't think I did, at the time.

Clare: And later?

Arthur: Later I understood it quite well. By which point the — the pattern had established itself. You were almost grown.

Clare: [quietly] Do you wish you'd made different choices?

Arthur: [very long pause] I find that question — I want to answer it honestly and I'm concerned it will sound as if I'm not.

Clare: Try.

Arthur: [slowly] I don't know how to wish differently about something that was, at the time, the only version of myself I knew how to be. I was a person who worked. That was clear to me in a way that almost nothing else was. What wasn't clear — or what I perhaps chose not to look at clearly — was the cost of that. [pause] I think I understood the cost in some approximate way and decided, in whatever way one decides things without quite deciding them, that it was acceptable. [long pause] I don't think it was acceptable. I think it was a failure of imagination. Not of feeling — of imagination.

Clare: [quietly] Okay.

Arthur: I'm sorry. I mean that plainly. Not as part of the interview.

Clare: [pause] I know, Dad.

[A long silence. The recorder continues. Outside, a gull calls twice. Clare eventually reaches over and turns it off.]
TRANSCRIPT;
    }
}
