XMPPPHP that works with Prosody
---


This is version of XMPPPHP that CAN use digest-md5 auth mechanism.

Applied patch from http://code.google.com/p/xmpphp/issues/detail?id=67&q=md5

Regular Prosody setup cannot handle PLAIN auth for some reason, so this is a good substitute.

Regular ejabberd setup cannot handle PLAIN auth too and needs a fix of realm.

Code has been cleaned by rules of PHP Mess Detector (http://phpmd.org/).

Finally.

