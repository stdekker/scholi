import { startStimulusApp } from '@symfony/stimulus-bundle';

// Controllers in assets/controllers/ are registered automatically (see controllers.json for UX packages).
// To register a custom 3rd-party controller, keep the return value:
//   const app = startStimulusApp();
//   app.register('some_controller_name', SomeImportedController);
startStimulusApp();
