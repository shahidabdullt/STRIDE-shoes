// Entry point.
import { initCartForms } from './cart.js';
import { initChat } from './chat/widget.js';
import { initConfirmForms } from './confirm.js';
import { initDrawers } from './drawer.js';
import { initVoice } from './voice/voice-panel.js';

initCartForms();
initConfirmForms();
initDrawers();
initChat();
initVoice();
