# “Fencepost” Ranking

### What problem is being solved?

At the macro level, feeds and streams are required to have a chronology. That is not to say that they are element-by-element chronological, but rather that they have an overall orientation — users scroll up to refresh, and expect therefore that content they have not yet seen appears "above" content they have already seen. Conversely, users scroll down to observe history. This means that the ordering of things they have already seen should be consistent where possible, so as to make history meaningful and comprehendible, especially across short time distances.

Generalized raking is implemented as any permutation of a sequence of elements. Therefore, generalized ranking is not enough to enforce overall chronology. In the most simple case, think of a "ranker" that orders elements by their ascending timestamp. In this case, the newest elements would appear on the bottom.

### Synthetic Chronology

If we want to provide an overall orientation, we need to define a chronology. The way this has historically been accomplished is to use actual element timestamps as this chronology. This is deterministic and guaranteed to be consistent. However, this is contradictory to our desire for arbitrary control over re-ranking.

In order to allow developers to implement arbitrary rankers without needing to themselves worry about how to guarantee orientation, we need to introduce a synthetic chronology. Fencepost ranking uses the history (and timestamps) of first-page-loads (“fenceposts”) as the overall chronology, and fills in the content between them deterministically by using a combination of cached and deterministically regenerable data.

### Overall Design

A fence is a per-stream-template server-side data structure which stores the history of first-page loads represented as fenceposts. The fencepost provides enough supporting metadata to allow a consistent ranking of elements between it and the next fencepost, some of which is allowed to be fully generalized and arbitrary ranking.

![image](https://github.com/Automattic/stream-builder/assets/69607277/b1eb87a6-c07b-47b0-bc2a-710e59b0bbfc)

The data stored for a **fence** is simple and ephemeral:

- Given a user and a template, the fence merely stores the **timestamp of the newest committed fencepost**, referred to as latest.
- If there is no such fence, it is assumed that the user has no committed fenceposts (latest = **null**)

A **fencepost** represents a segment of the fence, starting at some concrete specified time, as well as the following metadata:

- The **head**, an arbitrarily-ranked, fixed size (upper bound) window of elements, guaranteed non-empty.
- The **tail cursor**, which is a cursor that is able to enumerate the remainder of items not included in the head, but which fall (chronologically) between the head and the **next fencepost timestamp**.
- The **next fencepost timestamp**, which may be null if the user is known to have no previous fencepost.

![image](https://github.com/Automattic/stream-builder/assets/69607277/03af53bb-881d-4fa6-a422-a528109ce227)

Once fenceposts are committed, they are immutable and are never changed. This guarantees that any storage methodology leveraging LRU (least recently used) eviction is going to discard older fenceposts before newer ones.

There are two types of **fencepost cursor**, as well as the classical definition of a nullcursor:

- A null cursor is used when loading the first page, as per standard stream building
- A **head cursor** indicates that pagination has fallen within the head of a fencepost

**Fenceposts** therefore form a linked list of history blocks:

Enumeration Flow

- First page (cursor = null)
- Later page (cursor not null)
