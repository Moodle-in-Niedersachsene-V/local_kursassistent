# Course assistant (local_kursassistent)

Moodle plugin by [Moodle in Niedersachsen e. V.](https://moodle-ni.de)

[Deutsche Version](README.de.md)

The course assistant helps teachers build courses. Instead of creating text fields, icons
and formatting by hand, they pick prepared building blocks in the course, such as "learning
goals", "materials", "video" or "assignment", and only fill in the content. Site administrators
can extend the building blocks freely.

## Features

- **Learning information**: text blocks with icon and heading (learning goals, prerequisites, tips, ...)
- **Learning material**: files and images through the Moodle file picker, videos through a
  PeerTube selection, direct upload or an external link
- **Activities**: jump to Moodle's "Add an activity" with the type and target section preselected
- **Course setup**: change the course format, apply the content of a course template, apply
  and save section templates
- **Learning paths, competencies and statistics**: completion tracking and prerequisites, linear
  learning paths, an overview with visibility, reordering by drag and drop and duplicating
  (also into another course), assigning competencies from the Exabis competence grid, and
  statistics with a status per person and activity (done, in progress, open, overdue), filters
  and notes on participants, general or per activity
- **My progress**: participants see their own completion progress in the course with the status
  of each activity, their next step and collapsible sections
- **Work planner (block_workplanner)**: if the block is installed, learners see course infos, teacher notes per activity and the competencies of the activities there. Competencies are awarded in Exabis automatically once a learner has completed all activities a competency is assigned to (can be switched off in the settings)
- **Competency status**: with Exabis installed, the dashboard shows which learners have achieved the competencies assigned to activities
- **Course infos**: write a short message to all learners or to selected learners; learners read it under "My progress"
- **Dashboard**: also reachable from the main navigation; choose the course from a list, see key figures (students, progress, overdue activities, next date) and who needs attention, and open the areas of the assistant
- **Section management**: rename or create course sections directly in the assistant
- **Guide**: help text for teachers that administrators can edit freely

## Requirements

| Requirement | Value |
|---|---|
| Moodle | 5.1 or later (`requires` 2025041400); from 5.2 on the new course module interface of Moodle is used |
| PHP | 8.3 or 8.4 |
| Database | MariaDB, MySQL or PostgreSQL |

Optional additional plugins extend the features. If they are missing, the assistant hides the
affected building blocks. There is no hard dependency and no required installation order.

| Plugin | Adds |
|---|---|
| `repository_peertubeoauth` | Selection of existing PeerTube videos |
| `local_peertubeupload` | Video upload by the teacher |
| `repository_nextclouddirect` | Nextcloud as a location in the file picker |
| `block_exacomp` | "Competencies" column in the overview (assign and remove) |

## Installation

1. Upload the ZIP through Site administration > Plugins > Install plugins, or unpack the
   folder to `local/kursassistent/`.
2. Run the database upgrade.
3. Under Site administration > Plugins > Local plugins > Course assistant, check the building
   blocks and add more if needed.

## Documentation

- [Guide for teachers and administrators](docs/Anleitung.md) (German)

## License

GNU GPL v3 or later. See [http://www.gnu.org/copyleft/gpl.html](http://www.gnu.org/copyleft/gpl.html).
