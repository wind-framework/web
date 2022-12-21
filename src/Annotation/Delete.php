<?php

namespace Wind\Web\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Delete extends AnnotationRoute {}
