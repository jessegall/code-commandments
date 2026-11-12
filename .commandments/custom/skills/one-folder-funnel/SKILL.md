---
name: One folder funnel
description: Only package walk lists or walks the project's folders; read before listing a folder, walking a tree or reading a folder's entries anywhere in this project.
---

# One folder funnel

Package `walk` is the one place that steps into a folder of the project being judged. Its verdict refuses a step
into a link, a hidden folder, a dependency folder, a virtualenv, a build output beside its marker, a worktree and
an excluded folder, and it never reads a linked file. Every other walk asks it: `walk.Files`, `walk.Folders`,
`walk.Entries` for one folder, `walk.Generated` for the one named look inside a build folder.

The rule exists because the project once walked folders in eight places, one per bridge and a few more in the CLI,
each with its own idea of what to skip. Links were followed in some and not in others, and every fix had to land
five times. A bridge never walks a folder: Go hands it the exact files to read (`bridge.Asked`), and it refuses a
folder.

The rule is one file per judged language, each naming the calls by what they reach, never by the name written: Go
(`filepath.WalkDir`, `os.ReadDir`, ...), Python (`os.walk`, `os.scandir`, `glob.glob`, ...), PHP (`scandir`, `glob`,
and a construction of a `*DirectoryIterator`) and C# (`Directory.Enumerate*`, `DirectoryInfo.Get*s`). The frontend
bridge's JavaScript is not judged here, so it has none.

When the rule fires:

- The code reads the project: call the funnel instead, with the filter for what you want (`walk.Files(root,
  excluded, wanted)`).
- The code reads the tool's own data (an extracted bundle, a cache folder, the skill library, a test fixture):
  that folder is an allowed place, named in the rule file's `reject` list, not in code. Add its folder there.
