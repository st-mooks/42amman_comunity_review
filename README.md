# 42 Amman · Peer Rating Platform

Students rate each other from 1 to 3 stars with labels. Staff follow participation, analyse the results and review unusual voting. The system flags suspicious patterns in plain language, and staff decide whether to invalidate the votes.

**Live demo:** https://42rating.plainbitlabs.com

> **New to web development?** Read the [Beginner's Guide](docs/BEGINNERS_GUIDE.md). It explains every part of this project from zero: the words, how a click travels from the browser to the database, every folder and table, how cheating is detected, how to run it on your own computer and how to put it online.

| Role | Login | Password |
|---|---|---|
| Staff | `staff` | `Staff42!` |
| Student (in a "friends boosting" ring) | `lhaddad` | `Student42!` |
| Student (extreme voter) | `rjaber` | `Student42!` |
| Student (has not voted yet) | `mfakhour` | `Student42!` |

All demo people and data are fictional.

---

## What it does

### Students
- Search a peer by name or Intra login and rate them **1–3 stars**.
- The **star level controls which labels appear** (e.g. *Helpful* for 2–3★, *Late* for 1–2★, *Aggressive behavior* for 1★ only).
- Confirmation step before submitting; a rating can't be changed afterwards.
- Dashboard with **their own star average only**, **votes left**, and a **live countdown** to the deadline.
- Can't rate themselves, can't rate the same person twice in one window, can't go over the vote limit.

### Staff
- **Dashboard:** number of students, how many voted and how many did not, total ratings, ratings by stars, top labels, recent activity, unusual-activity summary.
- **Students:** who voted and who did not (filter by window), votes given and received, average. Click a student to see **who they rated, who rated them, and the labels they received**.
- **Ratings:** every rating with filters (student, given/received, stars, label, status), plus **grouped by label** and **grouped by stars**.
- **Labels:** add, edit, enable/disable and delete labels, and set the star range each label is offered for.
- **Voting window:** change dates, **minimum and maximum votes**, and status at any time; create new windows.
- **Export (CSV):** all ratings of a window, opens directly in Excel (Arabic names display correctly).
- **Abuse Review:** all unusual cases in one place, explained in plain language, with the students involved and an **Invalidate** button.

---

## Anti-abuse approach

Detection lives in [`config/abuse.php`](config/abuse.php). Every time staff open the Abuse Review page, the system reloads all votes **and all vote attempts** of the window and runs 8 small detectors, one for each pattern in the brief:

| Pattern | What triggers it (thresholds are at the top of the file) |
|---|---|
| **Friends boosting** | 3+ students who all gave each other 3 stars (found as a connected group of mutual 3-star votes) |
| **Pair voting / revenge** | Two students rating each other: mutual 3 stars, a 1-star answer after receiving a low vote, or both votes within 10 minutes |
| **Group voting** | 3+ students giving one student the **same** stars within 15 minutes |
| **Bulk votes** | 5+ votes landing on one student within 30 minutes |
| **Too fast / too often** | 4+ votes within 3 minutes, or far more attempts than the vote limit allows |
| **Repeated votes** | 3+ attempts to rate the same student (rejected attempts are logged in `vote_attempts`) |
| **Extreme voter** | 4+ votes that are all 1 star or all 3 stars |
| **Malicious serious vote** | A 1-star vote with a serious label, while 3+ others rated that student 2.5+ and nobody else reported a problem |

Design choices:
- **Nothing is automatic.** Detection only points staff at patterns. Staff confirm (invalidate the votes) or mark a case as a false alarm.
- **Plain language, not algorithm output.** Every case is a sentence like *"4 students gave Hamza Qasem exactly 1 star within 6 minutes"*, plus who is suspected, who is affected, the votes involved and the time window.
- **Decisions are remembered.** Cases are stored in `abuse_flags` (with their students and votes) under a stable key, so a dismissed case stays dismissed when detection runs again.
- **Invalidated votes stop counting everywhere** (averages, stats, CSV status), are kept for the record, can be restored, and every action is written to `audit_logs` with the staff member's name.
- **Rejected attempts are evidence.** Duplicate or over-the-limit votes never reach `ratings`, so every attempt is logged in `vote_attempts`. That's what makes "repeated votes" and "too often" detectable.
- The demo seeder plants one example of every pattern, and the background votes are built so they don't trigger any detector by accident. On the demo data the system finds **exactly the 8 planted cases and no false alarms**.

---

## Privacy & security

- **Role checks on the server for every endpoint** (`require_student()` / `require_staff()` in `config/auth.php`). Hiding something in the UI is never the protection.
- Students can only read **their own average**. No endpoint returns another student's rating or any labels to a student. Staff APIs answer `403` to students and `401` to visitors who aren't logged in.
- Passwords are stored as **bcrypt hashes** (`password_hash`). Accounts are created by staff only, because open sign-up would allow fake accounts for boosting.
- **Prepared statements** for every query (no SQL injection). Real database errors go to the server log only; users see a generic message.
- Session cookie: `HttpOnly`, `SameSite=Lax`, `Secure` behind HTTPS. Write requests must be JSON, which blocks cross-site form posts (CSRF).
- Output is escaped in the browser (no XSS from names or labels). CSV cells are protected against spreadsheet formula injection.
- Database credentials live in `config/config.local.php`, which is **not in git**. Apache blocks `.git`, `config`, `database`, `validator` and `deploy` from the web.

---

## Project structure

```
api/
  auth/      login, logout, me, register (staff-only account creation)
  student/   students, labels, voting_window, remaining_votes, my_average, submit_rating
  staff/     dashboard, students, student, reviews, grouped, labels, windows,
             abuse, abuse_action, export_csv, audit
config/      db connection, session, auth checks, helpers, abuse detection
database/    schema.sql (tables + labels), seed.php (demo data)
assets/      css, js (no framework, no build step), images
student/     dashboard, rate a peer
staff/       dashboard, students, ratings, abuse review, labels, voting window
deploy/      Apache site config
login.html, index.php
```

Stack: PHP 8 + MariaDB/MySQL, plain HTML/CSS/JavaScript. The frontend and the API share one domain, so the session cookie works without CORS.

---

## Run it locally

1. Create the database and a user (MariaDB/MySQL):
   ```sql
   CREATE DATABASE voting_system_42 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'rating'@'localhost' IDENTIFIED BY 'choose-a-password';
   GRANT ALL ON voting_system_42.* TO 'rating'@'localhost';
   ```
2. Import the schema: `mariadb -u rating -p voting_system_42 < database/schema.sql`
3. Copy `config/config.example.php` to `config/config.local.php` and fill in your values (XAMPP: user `root`, empty password, port `3306`).
4. Load the demo data: `php database/seed.php` (wipes users and ratings; keeps labels).
5. Start a server from the repo root: `php -S localhost:8000`, then open http://localhost:8000

## Deployment (live demo)

The live site runs on a Jetson Orin Nano (Ubuntu, Apache + PHP 8.3 + MariaDB 10.11), published through a **Cloudflare Tunnel**: no open router ports, and HTTPS handled by Cloudflare.

```
Browser ──HTTPS──▶ Cloudflare ══ tunnel ══▶ cloudflared (Jetson) ──▶ Apache :80 ──▶ /var/www/rating
```

```bash
sudo mkdir -p /var/www/rating && sudo chown $USER:$USER /var/www/rating
git clone git@github.com:st-mooks/42amman_comunity_review.git /var/www/rating
cd /var/www/rating
cp config/config.example.php config/config.local.php        # then edit it
sudo chgrp www-data config/config.local.php && chmod 640 config/config.local.php
sudo cp deploy/apache-rating.conf /etc/apache2/sites-available/rating.conf
sudo a2enmod headers && sudo a2ensite rating && sudo a2dissite 000-default
sudo apache2ctl configtest && sudo systemctl reload apache2
```
Update the live site: `cd /var/www/rating && git pull`.

## Team

- **Raghad Alshrawneh:** database design (ERD, tables, constraints)
- **Dana Farhan:** backend: authentication, student endpoints, rating submission
- **Malek Badarin:** UI/UX design (login, student and staff portals, abuse review)
- **Marwan Alfarra:** DevOps and integration: repository setup and cleanup, configuration, deployment on the Jetson with Cloudflare Tunnel
