"""Pruebas del instalador rápido, sin tocar la instalación real del usuario."""
import io
import json
import os
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import Mock, patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import instalar


class InstallerTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='netlinks-quick-')
        self.root = Path(self.temp.name)

    def tearDown(self):
        self.temp.cleanup()

    def fixture(self, system='Linux'):
        xampp = self.root / system
        (xampp / 'htdocs').mkdir(parents=True)
        php = instalar.php_path(xampp, system)
        php.parent.mkdir(parents=True, exist_ok=True)
        php.write_text('PHP fixture')
        source = self.root / 'source'
        source.mkdir(exist_ok=True)
        for part in instalar.FILES:
            if '.' in part:
                (source / part).write_text('fixture')
            else:
                (source / part).mkdir(exist_ok=True)
        for script in ('install-local.php','migrate-passwords.php','create-demo.php','check-install.php'):
            (source / 'bin' / script).write_text('PHP fixture')
        (source / 'app/bootstrap.php').write_text('PHP fixture')
        (source / 'public/index.php').write_text('PHP fixture')
        (source / 'views/home.php').write_text('PHP fixture')
        return xampp, source

    def test_detects_platforms(self):
        for system in ('Windows','Linux','Darwin'):
            with self.subTest(system=system):
                root, _ = self.fixture(system)
                self.assertEqual(instalar.find_xampp(system, str(root)), root.resolve())

    def test_missing_xampp_stops(self):
        with self.assertRaises(instalar.InstallError):
            instalar.find_xampp('Linux', str(self.root/'missing'))

    def test_confirmation_and_color(self):
        for value, expected in [('',True),('S',True),('n',False)]:
            with patch('builtins.input', return_value=value):
                self.assertEqual(instalar.confirm('¿Instalar?'), expected)
        with patch.object(instalar, 'COLOR', True), patch('sys.stdout', new_callable=io.StringIO) as output:
            instalar.say('Netlinks', '1;36')
            self.assertIn('\033[1;36mNetlinks', output.getvalue())

    def test_defaults_and_preserved_env(self):
        target = self.root/'site'
        target.mkdir()
        values = instalar.settings(target)
        self.assertEqual(values['DB_NAME'], 'bookgo_lite')
        self.assertEqual(values['DB_PASSWORD'], '')
        (target/'.env').write_text('# local\nCUSTOM=keep\nDB_PORT=3307\nDB_PASSWORD=fake-only\n')
        values = instalar.settings(target)
        instalar.write_env(target/'.env', values)
        self.assertEqual(values['DB_PORT'], '3307')
        self.assertIn('CUSTOM=keep', (target/'.env').read_text())
        self.assertIn('# local', (target/'.env').read_text())

    def test_remote_connection_rejected(self):
        target = self.root/'site'
        target.mkdir()
        (target/'.env').write_text('DB_HOST=example.org\n')
        with self.assertRaises(instalar.InstallError):
            instalar.settings(target)

    def test_php_receives_secrets_only_via_stdin(self):
        xampp, source = self.fixture()
        values = instalar.settings(xampp/'htdocs'/instalar.PROJECT)
        values['DB_PASSWORD']='fake-private-value'
        with patch.object(instalar.subprocess, 'run', return_value=Mock(returncode=0, stdout=b'{"ok":true,"state":"new"}')) as run:
            self.assertEqual(instalar.prepare_database(instalar.php_path(xampp,'Linux'),source,xampp,values),'new')
        self.assertNotIn('fake-private-value', ' '.join(run.call_args.args[0]))
        self.assertEqual(json.loads(run.call_args.kwargs['input'])['DB_PASSWORD'],'fake-private-value')

    def test_cancellation_changes_nothing(self):
        xampp, source = self.fixture()
        with patch.object(instalar,'confirm',return_value=False), patch.object(instalar,'prepare_database') as prepare:
            instalar.install(source,xampp,'Linux',False)
        prepare.assert_not_called()
        self.assertFalse((xampp/'htdocs'/instalar.PROJECT).exists())

    def test_install_preserves_local_files_without_backups(self):
        xampp, source = self.fixture()
        target=xampp/'htdocs'/instalar.PROJECT
        (target/'app').mkdir(parents=True)
        (target/'public').mkdir()
        (target/'app/bootstrap.php').write_text('old')
        (target/'public/index.php').write_text('old')
        (target/'local.txt').write_text('keep')
        (target/'.env').write_text('DB_PASSWORD=fake-only\n')
        other=xampp/'htdocs/other'
        other.mkdir()
        (other/'index.php').write_text('do not touch')
        with patch.object(instalar,'confirm',return_value=True), patch.object(instalar,'prepare_database',return_value='existing'), patch.object(instalar,'finish_database'):
            instalar.install(source,xampp,'Linux',False)
        self.assertEqual((target/'local.txt').read_text(),'keep')
        self.assertEqual(instalar.read_env(target/'.env')['DB_PASSWORD'],'fake-only')
        self.assertEqual((other/'index.php').read_text(),'do not touch')
        self.assertEqual(sorted(p.name for p in (xampp/'htdocs').iterdir()), [instalar.PROJECT,'other'])
        self.assertTrue((source/'views/home.php').exists())

    def test_unrelated_destination_not_overwritten(self):
        xampp, source = self.fixture()
        target=xampp/'htdocs'/instalar.PROJECT
        target.mkdir()
        (target/'other.txt').write_text('keep')
        with self.assertRaises(instalar.InstallError):
            instalar.validate_site(source,target)
        self.assertEqual((target/'other.txt').read_text(),'keep')

    def test_password_prompt_only_when_auth_fails(self):
        xampp, source = self.fixture()
        with patch.object(instalar,'confirm',return_value=True), patch.object(instalar,'prepare_database',side_effect=[PermissionError(), 'new']) as prepare, \
             patch.object(instalar.getpass,'getpass',return_value='fake-only') as password, patch.object(instalar,'finish_database'):
            instalar.install(source,xampp,'Linux',False)
        password.assert_called_once()
        self.assertEqual(prepare.call_count,2)

    def test_database_failure_does_not_copy(self):
        xampp, source = self.fixture()
        with patch.object(instalar,'confirm',return_value=True), patch.object(instalar,'prepare_database',side_effect=instalar.InstallError('failed')), self.assertRaises(instalar.InstallError):
            instalar.install(source,xampp,'Linux',False)
        self.assertFalse((xampp/'htdocs'/instalar.PROJECT).exists())


if __name__=='__main__':
    unittest.main()
