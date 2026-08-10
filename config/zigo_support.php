<?php
return ['attachment_disk'=>env('ZIGO_SUPPORT_ATTACHMENT_DISK','local'),'attachment_max_kb'=>(int)env('ZIGO_SUPPORT_ATTACHMENT_MAX_KB',5120),'allowed_mimes'=>['image/jpeg','image/png','image/webp','application/pdf']];
