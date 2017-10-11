<?php namespace Gonpre\Docx;

use Illuminate\Support\ServiceProvider;

class DocxServiceProvider extends ServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->publishes([
            __DIR__.'/../../config/docx.php' => config_path('docx.php'),
        ]);
    }
}
