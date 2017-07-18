Tracks Australian movie release dates.

  * Shows movie information in a web page.

  * Sends a daily email if any release dates move, or there are new movies to
    flag interest in.

  * Writes entries to a Google calendar.

  * Interfaces with themoviedb.org for plot summary, cast and crew info and
    such.

**Warning:** Do not put this on a web server that is visible to the Internet! It
is a little project that I threw together for personal use, to run on my local
computer behind my home network firewall. It's pretty rough in places, and the
UI is clunky but functional. A few people
[asked](https://news.ycombinator.com/item?id=14784867) if I could share the
source, so here it 'tis.

Requirements:

  * PHP

  * PostgreSQL

  * A web-server running PHP (e.g., Apache or Nginx)

  * [Liquibase](http://www.liquibase.org/) for database schema management (and
    hence, Java to run Liquibase). Only needed during initial setup.

To Setup:

  * Run `composer install` to install PHP dependencies.

  * Create a database in PostgreSQL for the movie data.

  * From the `database` folder, run Liquibase on the `db.xml` change-log file
    to create the database schema.

    See the `database/sample_run.txt` file for a bit of a hand with how to do
    that. Copy it to `run.sh`, fill in the required database variables, then
    run `run.sh update` to generate the schema.

  * In the `web` folder, copy `config-sample.php` to `config.php` and fill in
    the variables.

    The `BASE_URL` is the URL you'll use to access the web page. e.g.
    `http://localhost/movies/`

    The `ADMIN_EMAIL` is who will receive the daily email if there are any
    changes.

    See [this
    link](https://developers.google.com/identity/sign-in/web/devconsole-project)
    for get a client ID and secret to access Google Calendar:

    To get the `TMDB_API_KEY`, create an account on themoviedb.org if you don't
    already have one. Then go to the
    [settings->API](https://www.themoviedb.org/settings/api) page and apply for
    an API. Copy the v3 auth APK key into the configuration file.

  * Configure your web-server to point to the `web` folder of this project,
    such that it is whatever URL you put in for the `BASE_URL`.

  * Open the init.php page for the project. e.g., If the `BASE_URL` is
    `http://localhost/movies/` then go to `http://localhost/movies/init.php`.

      * Follow the instructions to get an access token.

      * Once that's done, select which calender you want movieschedule to
        maintain events in. (It may be worth creating a new calender just for
        movie entries, instead of having the clutter up your main calender.)

  * Once that's done, you should be able to go to the `BASE_URL` and see the
    main page with no movies in it. Do this, just to verify that everything
    looks like it is working.

  * Now manually open the `cron.php` page to do the first load of all the
    movies.  This will take a while as there is a lot to load. If it completes
    successfully there will be no output. You can then go back to the
    `BASE_URL` page and start marking which movies you want to watch and which
    you want to ignore.

  * Create a cron job to call `cron.php` daily. This will check for new movies
    or changes release dates, and send an email if anything changed.

    e.g.: `5 6 * * * wget -O - -q http://localhost/movies/cron.php`

(MIT License)
