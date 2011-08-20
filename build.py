import os
import os.path
import shutil
import jbuild

def build(update):
	# set distribution source
	folder = 'trunk'
	contentpath = os.path.join(os.getcwd(), folder, 'plg_content_sigplus')
	searchpath = os.path.join(os.getcwd(), folder, 'plg_search_sigplus')
	buttonpath = os.path.join(os.getcwd(), folder, 'plg_button_sigplus')
	modulepath = os.path.join(os.getcwd(), folder, 'mod_sigplus')
	componentpath = os.path.join(os.getcwd(), folder, 'com_sigplus')
	zippackagepath = os.path.join(os.getcwd(), folder, 'zip')

	# minify javascript sources
	print('Minifying javascript files...')
	jbuild.minify(os.getcwd())

	# get (and update) version
	version = jbuild.get_version(zippackagepath, 'pkg_sigplus.xml', update)
	#jbuild.update_languages(contentpath, 'sigplus.xml')
	#jbuild.update_languages(searchpath, 'sigplus.xml')
	#jbuild.update_languages(modulepath, 'mod_sigplus.xml')

	# package content plug-in for distribution
	print('Packaging content plug-in...')
	jbuild.package(contentpath, 'plg_content_sigplus.ar.zip', version, os.path.join(folder, 'zip'))

	# package search plug-in for distribution
	print('Packaging search plug-in...')
	jbuild.package(searchpath, 'plg_search_sigplus.ar.zip', version, os.path.join(folder, 'zip'))

	# package editor button plug-in for distribution
	print('Packaging search plug-in...')
	jbuild.package(buttonpath, 'plg_button_sigplus.ar.zip', version, os.path.join(folder, 'zip'))

	# package module for distribution
	print('Packaging module...')
	jbuild.package(modulepath, 'mod_sigplus.ar.zip', version, os.path.join(folder, 'zip'))

	# package distribution
	print('Packaging distribution...')
	jbuild.package(zippackagepath, 'sigplus' + '-' + version.as_string(), version, folder)

reply = input('Update version number [y/n]?')
if reply.strip() in ['Yes','yes','Y','y']:
	print('Updating version number and repackaging...')
	build(True)
else:
	print('Repackaging...');
	build(False)
