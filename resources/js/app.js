import './bootstrap';
import './tema';

import Alpine from 'alpinejs';
import './editor';
import './surat-masuk';
import './pratinjau-pdf';
import { imporTabel } from './tabel-impor';

window.Alpine = Alpine;
Alpine.data('imporTabel', imporTabel);

Alpine.start();
