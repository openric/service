Dear Florence, dear all,

Thank you — this crystallises the thread nicely, and it matches where Silvia and
I landed: the tape is a carrier, not a Record Set. A few things I'd underline,
plus a practical note on the Carrier gap you raise.

**The layering you describe is the key move.** Separating the *content* of a
track (a rico:Record) from its *inscription* on the tape (a rico:Instantiation),
with a later copy or digitisation as another Instantiation of the *same* Record,
is exactly what lets Valentín's heterogeneous tapes be described without forcing
everything onto one axis. It also answers his frontend worry: the hierarchy the
user browses (session Record Sets, autonomous Records, the whole collection) is
the *intellectual* arrangement, while the tape sits on the *physical* side as the
shared carrier — so a track and its digitised copy can live in the same
intellectual place while pointing at different carriers.

And I agree the same-session items are Records, not Record Parts — Record Part
being a constituent of a Record, which an independently-documenting track isn't.

**On the missing Carrier entity.** This is the one point where RiC-O 1.1 makes
you work: `rico:hasCarrierType` gives you the *kind* of medium, and the extent
relations describe *how much*, but there's no handle on the individual physical
object — "this tape, shelf TAP-0042", with its own condition and custody history.
As you say, that's a RiC-O 2.0 item; in the meantime you suggested extending with
a Carrier subclass of Thing, so we've done exactly that in OpenRiC (the open
reference implementation at ric.theahg.co.za):

    openricx:Carrier      rdfs:subClassOf rico:Thing .
    openricx:hasCarrier   rdfs:domain rico:Instantiation ; rdfs:range openricx:Carrier .

Because one carrier can bear several inscriptions, `hasCarrier` is many-to-one
from Instantiation to Carrier — so all of a tape's tracks point at one shared
Carrier node, and a custody transfer or a migration-to-digital Event attaches to
the physical tape rather than being duplicated across every track. We keep
`rico:hasCarrierType` alongside it (the kind), so nothing about the RiC-O 1.1
description is lost. When RiC-O 2.0 lands its Carrier class we'll bind ours to it
with `owl:equivalentClass` and existing data keeps working.

If it's useful to anyone on the list, the extension (Turtle + SHACL) is small and
open (AGPL) — happy to share the files or a link. It sits next to another small
extension we added this week for the "role an agent plays in a relation" gap, so
we're glad to feed real-world stress-tests of both back to EGAD as you work
towards 1.2 and 2.0.

Groete / Regards,

Johan Pieterse
http://heratio.theahg.co.za/  ·  https://ric.theahg.co.za/
