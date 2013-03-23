Subject Index plugin for Dokuwiki:
====================
Create subject index entries "anywhere" in your wiki pages, that will then be compiled into a Subject Index page, listed A-Z, by headings, then entries/links. I personally find that this well replaces tagging... and generates a much more useful, readable index at the end, when compared with "tag-clouds".

See the Dokuwiki [SubjectIndex plugin page](https://www.dokuwiki.org/plugin:subjectindex) for full details

Adding a subject index entry:
-----------------------------
Syntax:

    {{entry>[index/heading/subheading/]entry[|display text]}}
    {{entry>books/fiction/writing novels|}}
    {{entry>1/book/binding}

 *[..] = optional

The first example above would create a new subject index entry as follows:

    B => books => fiction => writing novels [page link]

The page link would point to the default index page (first one in config list) as no number was provided

Breakdown of entry elements:
----------------------------
- index           : which Subject Index page should the entry be added to (defaults to first one in list)
- heading         : the main heading in the subject index, under which entry will be shown, first letter is used for A-Z headings
- subheading      : as above
- entry           : the actual entry text, a meaning description of what this entry is about
- display text    : what should be visible on the page; can be different text, or the heading/entry text

By default only a small magnifying glass icon is displayed on the page, but you can also show text next to the entry, or show the entry itself.
To display the whole entry text on the page use ...|:: or ...|*:: , both will work.

Entries are automatically indexed on each page save, and saved by default in the *"../data/index/subject.idx"* file, along with other Dokuwiki search indexes.  his location can be changed on the Admin configuration page, but this is not recommended.

Configuration:
==============
Viewing the Subject Index:
--------------------------
Syntax:

    {{subjectindex>[abstract;border;cols=?;index=?;proper;title]}}

 *[..] = optional
- abstract : show abstract of page content as a tool-tip
- border   : show borders around table columns
- cols=?   : number of columns on the index page or the column width (use CSS units)
- index=?  : [0-9] the Subject-Index page on which to list the entry
- proper   : use proper-case for wiki page names and entries
- title    : use title (1st heading) instead of name for links
- section  : section of index to be displayed
- default  : is this wiki page the default target for entries in this section

Put this markup on a new page, save, and you should see a new Subject Index for your wiki.

Don't forget to put `~~NOCACHE~~` somewhere on the page if you want immediate updates!