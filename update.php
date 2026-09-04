<?php

// Bei einem Versions-Update ruft REDAXO nur update.php auf, nicht install.php erneut - das Modul
// muss daher hier ebenfalls synchronisiert werden, sonst kommen spätere Änderungen an
// module/module_output.inc bzw. module/module_input.inc bei bestehenden Installationen nie an.
\Buechsenlicht\Installer::syncModule();
